<?php
declare(strict_types=1);

function load_env_file(): array
{
    $paths = [
        __DIR__ . '/../.env',
        __DIR__ . '/.env',
    ];

    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];
            $value = trim($matches[2]);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    return [];
}

function env_value(array $env, string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return trim((string) $value);
    }

    return trim($env[$key] ?? $default);
}

function clean_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function extract_email(string $value): string
{
    if (preg_match('/<([^<>]+)>/', $value, $matches)) {
        return trim($matches[1]);
    }

    return trim($value);
}

function encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function render_page(bool $success, string $title, string $message): void
{
    http_response_code($success ? 200 : 400);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $backUrl = '/contact';

    if (!empty($_SERVER['HTTP_REFERER'])) {
        $referer = (string) $_SERVER['HTTP_REFERER'];
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';

        if ($refererHost === null || $refererHost === $currentHost) {
            $backUrl = $referer;
        }
    }

    $safeBackUrl = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle}</title>
    <style>
      body {
        min-height: 100vh;
        margin: 0;
        display: grid;
        place-items: center;
        background: #ebe6e3;
        color: #262321;
        font-family: Arial, sans-serif;
      }

      main {
        width: min(90vw, 540px);
        padding: 42px;
        border-radius: 24px;
        background: #111;
        color: #fff;
        text-align: center;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
      }

      h1 {
        margin: 0 0 18px;
        font-size: 28px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      p {
        margin: 0 0 28px;
        line-height: 1.6;
        color: #dedbd5;
      }

      a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 24px;
        border-radius: 999px;
        background: #d4b35a;
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-decoration: none;
        text-transform: uppercase;
      }
    </style>
  </head>
  <body>
    <main>
      <h1>{$safeTitle}</h1>
      <p>{$safeMessage}</p>
      <a href="{$safeBackUrl}">Volver</a>
    </main>
  </body>
</html>
HTML;
}

function smtp_read($socket): array
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    return [$code, $response];
}

function smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function smtp_expect($socket, array $expectedCodes, string $context): array
{
    [$code, $response] = smtp_read($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException($context . ': ' . trim($response));
    }

    return [$code, $response];
}

function smtp_send(array $config, array $recipients, string $message): void
{
    $host = $config['host'];
    $port = (int) $config['port'];
    $secure = $config['secure'];
    $user = $config['user'];
    $pass = $config['pass'];
    $from = $config['envelope_from'];
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $target = ($secure ? 'ssl://' : '') . $host . ':' . $port;

    $socket = stream_socket_client($target, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        throw new RuntimeException("No se pudo conectar a SMTP: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, 30);

    try {
        smtp_expect($socket, [220], 'Saludo SMTP');
        smtp_write($socket, 'EHLO ' . $serverName);
        smtp_expect($socket, [250], 'EHLO');

        if (!$secure && $port === 587) {
            smtp_write($socket, 'STARTTLS');
            smtp_expect($socket, [220], 'STARTTLS');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se pudo activar TLS');
            }

            smtp_write($socket, 'EHLO ' . $serverName);
            smtp_expect($socket, [250], 'EHLO TLS');
        }

        smtp_write($socket, 'AUTH LOGIN');
        smtp_expect($socket, [334], 'AUTH LOGIN');
        smtp_write($socket, base64_encode($user));
        smtp_expect($socket, [334], 'Usuario SMTP');
        smtp_write($socket, base64_encode($pass));
        smtp_expect($socket, [235], 'Password SMTP');

        smtp_write($socket, 'MAIL FROM:<' . $from . '>');
        smtp_expect($socket, [250], 'MAIL FROM');

        foreach ($recipients as $recipient) {
            smtp_write($socket, 'RCPT TO:<' . $recipient . '>');
            smtp_expect($socket, [250, 251], 'RCPT TO');
        }

        smtp_write($socket, 'DATA');
        smtp_expect($socket, [354], 'DATA');

        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250], 'Envio del mensaje');

        smtp_write($socket, 'QUIT');
        smtp_expect($socket, [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_page(false, 'Metodo no permitido', 'El formulario debe enviarse desde el sitio.');
    exit;
}

if (!empty($_POST['website'] ?? '')) {
    render_page(true, 'Consulta enviada', 'Gracias. Recibimos tu consulta y vamos a responderte a la brevedad.');
    exit;
}

$env = load_env_file();

$smtpHost = env_value($env, 'SMTP_HOST', 'smtp.gmail.com');
$smtpPort = env_value($env, 'SMTP_PORT', '587');
$smtpSecure = strtolower(env_value($env, 'SMTP_SECURE', 'false')) === 'true';
$smtpUser = trim(env_value($env, 'SMTP_USER'));
$smtpPass = preg_replace('/\s+/', '', env_value($env, 'SMTP_PASS'));
$mailToRaw = env_value($env, 'MAIL_TO');
$mailFrom = clean_header(env_value($env, 'MAIL_FROM', 'OSP Abogados <' . $smtpUser . '>'));
$subjectPrefix = clean_header(env_value($env, 'MAIL_SUBJECT_PREFIX', '[OSP Abogados]'));

$missingConfig = [];
foreach ([
    'SMTP_USER' => $smtpUser,
    'SMTP_PASS' => $smtpPass,
    'MAIL_TO' => $mailToRaw,
] as $key => $value) {
    if ($value === '') {
        $missingConfig[] = $key;
    }
}

if ($missingConfig !== []) {
    error_log('Formulario OSP: faltan variables ' . implode(', ', $missingConfig));
    render_page(false, 'No se pudo enviar', 'Falta configurar el envio de correo en el servidor.');
    exit;
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));
$fecha = trim((string) ($_POST['fecha'] ?? ''));
$detalle = trim((string) ($_POST['detalle'] ?? ''));
$formulario = trim((string) ($_POST['formulario'] ?? 'Formulario de contacto'));

if ($nombre === '' || $email === '' || $telefono === '' || $detalle === '') {
    render_page(false, 'Faltan datos', 'Por favor completa los campos obligatorios e intenta nuevamente.');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    render_page(false, 'Email invalido', 'Por favor revisa el correo electronico e intenta nuevamente.');
    exit;
}

$recipients = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $mailToRaw) ?: [])));
$recipients = array_values(array_filter($recipients, static fn(string $item): bool => (bool) filter_var($item, FILTER_VALIDATE_EMAIL)));

if ($recipients === []) {
    error_log('Formulario OSP: MAIL_TO no tiene destinatarios validos');
    render_page(false, 'No se pudo enviar', 'El destinatario del formulario no esta configurado correctamente.');
    exit;
}

$fromEmail = extract_email($mailFrom);
if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $mailFrom = 'OSP Abogados <' . $smtpUser . '>';
}

$subject = trim($subjectPrefix . ' Nueva consulta web');
$bodyLines = [
    'Nueva consulta recibida desde el sitio web.',
    '',
    'Formulario: ' . $formulario,
    'Nombre: ' . $nombre,
    'Email: ' . $email,
    'Telefono: ' . $telefono,
    'Fecha solicitada: ' . ($fecha !== '' ? $fecha : 'No indicada'),
    '',
    'Detalle:',
    $detalle,
];

$headers = [
    'Date: ' . date(DATE_RFC2822),
    'From: ' . $mailFrom,
    'Reply-To: ' . clean_header($nombre) . ' <' . clean_header($email) . '>',
    'To: ' . implode(', ', $recipients),
    'Subject: ' . encode_subject($subject),
    'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$message = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyLines);

try {
    smtp_send([
        'host' => $smtpHost,
        'port' => $smtpPort,
        'secure' => $smtpSecure,
        'user' => $smtpUser,
        'pass' => $smtpPass,
        'envelope_from' => $smtpUser,
    ], $recipients, $message);

    render_page(true, 'Consulta enviada', 'Gracias. Recibimos tu consulta y vamos a responderte a la brevedad.');
} catch (Throwable $exception) {
    error_log('Formulario OSP SMTP error: ' . $exception->getMessage());
    render_page(false, 'No se pudo enviar', 'Hubo un problema al enviar la consulta. Por favor intenta nuevamente o contactanos por WhatsApp.');
}
