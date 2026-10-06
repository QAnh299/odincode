// ── Đổi màu giao diện theo ý người dùng ──────────────────────────────
// Từ 1 màu chính, tính ra các biến CSS (màu nền sidebar, màu chữ trên
// nền màu chính...) rồi lưu vào localStorage. Script nhỏ trong <head>
// của layouts/app.blade.php áp lại các biến này trước khi vẽ trang.

const STORAGE_KEY = "odin.theme";
const DEFAULT_COLOR = "#005e12";
const ACCENT = "#fcda43";
const root = document.documentElement;

function hexToRgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function rgbToHex(rgb) {
    return "#" + rgb.map((c) => Math.round(c).toString(16).padStart(2, "0")).join("");
}

// Độ sáng tương đối theo WCAG (0 = đen, 1 = trắng)
function luminance(hex) {
    const [r, g, b] = hexToRgb(hex).map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function darken(hex, factor) {
    return rgbToHex(hexToRgb(hex).map((c) => c * factor));
}

function buildVars(color) {
    const isLight = luminance(color) > 0.4;
    const onPrimary = isLight ? "#1f2937" : "#ffffff";

    // Nền sidebar: làm tối màu chính đến khi đủ tối cho chữ trắng
    let sidebarBg = darken(color, 0.57);
    while (luminance(sidebarBg) > 0.05) {
        sidebarBg = darken(sidebarBg, 0.85);
    }

    return {
        "--odin-primary": color,
        "--odin-on-primary": onPrimary,
        "--odin-sidebar-bg": sidebarBg,
        "--odin-active-icon": isLight ? onPrimary : ACCENT,
    };
}

function readSaved() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || null;
    } catch (e) {
        return null;
    }
}

function syncPicker(color) {
    document.querySelectorAll("[data-odin-theme-color]").forEach((btn) => {
        const active = btn.dataset.odinThemeColor.toLowerCase() === color.toLowerCase();
        btn.setAttribute("aria-pressed", String(active));
    });
    document.querySelectorAll("[data-odin-theme-custom]").forEach((input) => {
        input.value = color;
    });
}

function applyTheme(color) {
    const vars = buildVars(color);
    Object.entries(vars).forEach(([name, value]) => root.style.setProperty(name, value));
    syncPicker(color);

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ color, vars }));
    } catch (e) {
        // Trình duyệt chặn localStorage: vẫn đổi màu được, chỉ không nhớ.
    }
}

function resetTheme() {
    Object.keys(buildVars(DEFAULT_COLOR)).forEach((name) => root.style.removeProperty(name));
    syncPicker(DEFAULT_COLOR);

    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}
}

document.addEventListener("click", (event) => {
    const swatch = event.target.closest("[data-odin-theme-color]");
    if (swatch) {
        applyTheme(swatch.dataset.odinThemeColor);
        return;
    }

    if (event.target.closest("[data-odin-theme-reset]")) {
        resetTheme();
    }
});

document.addEventListener("input", (event) => {
    if (event.target.matches("[data-odin-theme-custom]")) {
        applyTheme(event.target.value);
    }
});

syncPicker(readSaved()?.color || DEFAULT_COLOR);
