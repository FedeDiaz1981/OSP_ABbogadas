// public/js/ui.js

document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const navSection = document.querySelector(".section-navigation");
  const menuToggle = document.querySelector("[data-menu-toggle]");
  const bodyOverlay = document.querySelector("[data-body-overlay]");
  const navOverlay = document.querySelector("[data-nav-overlay]");
  const menuClose = navSection
    ? navSection.querySelector("[data-menu-close]")
    : null;

  // ================= MENÚ LATERAL =================
  if (navSection) {
    const openMenu = () => {
      navSection.classList.add("is-open");
      body.classList.add("has-open-menu");
      body.classList.add("no-scroll");

      if (menuToggle) menuToggle.classList.add("is-open");
      if (bodyOverlay) bodyOverlay.classList.add("is-active");
      if (navOverlay) navOverlay.classList.add("is-active");
    };

    const closeMenu = () => {
      navSection.classList.remove("is-open");
      body.classList.remove("has-open-menu");
      body.classList.remove("no-scroll");

      if (menuToggle) menuToggle.classList.remove("is-open");
      if (bodyOverlay) bodyOverlay.classList.remove("is-active");
      if (navOverlay) navOverlay.classList.remove("is-active");
    };

    // Botón hamburguesa
    if (menuToggle) {
      menuToggle.addEventListener("click", (e) => {
        e.preventDefault();

        if (navSection.classList.contains("is-open")) {
          closeMenu();
        } else {
          openMenu();
        }
      });
    }

    // Botón cerrar (div con data-menu-close)
    if (menuClose) {
      menuClose.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
      });
    }

    // Delegación por si el click cae en el <img> dentro de .menu-close
    document.addEventListener("click", (e) => {
      const closeBtn = e.target.closest("[data-menu-close]");
      if (!closeBtn) return;

      e.preventDefault();
      closeMenu();
    });

    // Overlays
    if (bodyOverlay) {
      bodyOverlay.addEventListener("click", () => {
        if (navSection.classList.contains("is-open")) {
          closeMenu();
        }
      });
    }

    if (navOverlay) {
      navOverlay.addEventListener("click", () => {
        if (navSection.classList.contains("is-open")) {
          closeMenu();
        }
      });
    }

    // Links del menú
    const menuLinks = navSection.querySelectorAll(".menu a");
    menuLinks.forEach((link) => {
      link.addEventListener("click", () => {
        closeMenu();
      });
    });

    // ESC para cerrar
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" || e.key === "Esc") {
        if (navSection.classList.contains("is-open")) {
          closeMenu();
        }
      }
    });
  }

  // ================= HERO: EFECTO TYPER =================

  const heroTitle = document.querySelector(".hero__title.js-title");
  if (heroTitle) {
    const wordsContainer = heroTitle.querySelector(".hero__words");
    const h1 = heroTitle.querySelector("h1");

    if (wordsContainer && h1) {
      const words = Array.from(wordsContainer.querySelectorAll("p"))
        .map((p) => p.textContent.trim())
        .filter((t) => t.length > 0);

      if (words.length > 0) {
        let wordIndex = 0;
        let charIndex = 0;

        const typingSpeed = 80;   // velocidad de tipeo (ms por letra)
        const wordPause = 1400;   // pausa con palabra completa
        const clearDelay = 300;   // pausa antes de borrar/cambiar

        const typeWord = () => {
          const word = words[wordIndex];

          // Aseguramos clases de entrada
          h1.classList.add("is-entering");
          h1.classList.remove("is-leaving");

          // Seteamos el texto parcial
          h1.textContent = word.slice(0, charIndex + 1);
          charIndex++;

          if (charIndex < word.length) {
            // Seguimos tipeando
            setTimeout(typeWord, typingSpeed);
          } else {
            // Palabra completa, dejamos un rato y luego cambiamos
            setTimeout(() => {
              // animación de salida
              h1.classList.remove("is-entering");
              h1.classList.add("is-leaving");

              setTimeout(() => {
                // pasamos a la siguiente palabra
                wordIndex = (wordIndex + 1) % words.length;
                charIndex = 0;
                h1.classList.remove("is-leaving");
                typeWord();
              }, clearDelay);
            }, wordPause);
          }
        };

        // Arrancamos con la primera palabra
        typeWord();
      }
    }
  }
});
