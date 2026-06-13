document.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-confirm]");

    if (!form) {
      return;
    }

    const message = form.dataset.confirm || "Czy na pewno chcesz wykonac te akcje?";

    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });

  const siteNav = document.querySelector("[data-site-nav]");
  const navToggle = document.querySelector("[data-site-nav-toggle]");
  const navMenu = document.querySelector("[data-site-nav-menu]");
  const menuIcon = document.querySelector("[data-menu-icon]");

  if (!siteNav || !navToggle || !navMenu) {
    return;
  }

  const setMenuOpen = (isOpen) => {
    siteNav.classList.toggle("site-nav--open", isOpen);
    document.body.classList.toggle("nav-open", isOpen);
    navToggle.setAttribute("aria-expanded", String(isOpen));
    navToggle.setAttribute("aria-label", isOpen ? "Zamknij menu" : "Otworz menu");
    navMenu.hidden = !isOpen && window.innerWidth < 768;

    if (menuIcon) {
      menuIcon.textContent = isOpen ? "close" : "menu";
    }
  };

  setMenuOpen(false);

  navToggle.addEventListener("click", () => {
    setMenuOpen(!siteNav.classList.contains("site-nav--open"));
  });

  navMenu.addEventListener("click", (event) => {
    if (event.target.closest("a")) {
      setMenuOpen(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      setMenuOpen(false);
    }
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth >= 768) {
      navMenu.hidden = false;
      setMenuOpen(false);
      return;
    }

    navMenu.hidden = !siteNav.classList.contains("site-nav--open");
  });
});
