// public/js/ui.js

document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const menuToggle = document.querySelector("[data-menu-toggle]");
  const menuClose = document.querySelector("[data-menu-close]");
  const navSection = document.querySelector(".section-navigation");
  const bodyOverlay = document.querySelector("[data-body-overlay]");
  const navOverlay = document.querySelector("[data-nav-overlay]");

  // Sin sección de navegación no tiene sentido seguir
  if (!navSection) return;

  const openMenu = () => {
    navSection.classList.add("is-open");

    // Clases de body como en el theme original
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

  // Toggle del botón hamburguesa
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

  // Botón de cerrar dentro del panel
  if (menuClose) {
    menuClose.addEventListener("click", (e) => {
      e.preventDefault();
      closeMenu();
    });
  }

  // Click en overlay del body → cerrar menú
  if (bodyOverlay) {
    bodyOverlay.addEventListener("click", () => {
      if (navSection.classList.contains("is-open")) {
        closeMenu();
      }
    });
  }

  // Si querés que el overlay interno también cierre
  if (navOverlay) {
    navOverlay.addEventListener("click", () => {
      if (navSection.classList.contains("is-open")) {
        closeMenu();
      }
    });
  }

  // ---------- MAIN MENU: links dentro del panel ----------
  const menuLinks = navSection.querySelectorAll(".menu a");

  menuLinks.forEach((link) => {
    link.addEventListener("click", () => {
      // Al hacer click en cualquier ítem del menú, cerramos el panel
      closeMenu();
    });
  });

  // ---------- Cerrar con tecla ESC ----------
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" || e.key === "Esc") {
      if (navSection.classList.contains("is-open")) {
        closeMenu();
      }
    }
  });
});
