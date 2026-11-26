const STORAGE_KEY = "odin.sidebar.collapsed";
const COLLAPSED_CLASS = "odin-sidebar-collapsed";
const root = document.documentElement;
const desktop = window.matchMedia("(min-width: 992px)");

function syncButtons() {
    const expanded = String(!root.classList.contains(COLLAPSED_CLASS));
    document
        .querySelectorAll("[data-odin-sidebar-toggle]")
        .forEach((btn) => btn.setAttribute("aria-expanded", expanded));
}

function setCollapsed(collapsed) {
    root.classList.toggle(COLLAPSED_CLASS, collapsed);
    syncButtons();

    try {
        localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0");
    } catch (e) {
    }
}

function setSubmenuOpen(toggle, open) {
    toggle.closest(".odin-menu__group").classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", String(open));
}

document.addEventListener("click", (event) => {
    if (event.target.closest("[data-odin-sidebar-toggle]")) {
        setCollapsed(!root.classList.contains(COLLAPSED_CLASS));
        return;
    }

    const submenuToggle = event.target.closest("[data-odin-submenu-toggle]");
    if (!submenuToggle) {
        return;
    }

    if (desktop.matches && root.classList.contains(COLLAPSED_CLASS)) {
        setCollapsed(false);
        setSubmenuOpen(submenuToggle, true);
        return;
    }

    setSubmenuOpen(submenuToggle, submenuToggle.getAttribute("aria-expanded") !== "true");
});

syncButtons();
