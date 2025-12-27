window.SATheme = (function () {
  const KEY = "sa_theme";
  function apply(theme) {
    const root = document.documentElement;
    let effective = theme;
    if (theme === "system") {
      effective = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    }
    root.setAttribute("data-bs-theme", effective);
    root.classList.toggle("theme-dark", effective === "dark");
  }
  function set(theme) {
    localStorage.setItem(KEY, theme);
    apply(theme);
    document.cookie = `${KEY}=${encodeURIComponent(theme)}; path=/; max-age=31536000`;
    const el = document.getElementById("saThemeCurrent");
    if (el) el.textContent = theme;
  }
  function get() {
    return localStorage.getItem(KEY) || (getCookie(KEY) || "light");
  }
  function getCookie(name) {
    const m = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
    return m ? decodeURIComponent(m[2]) : null;
  }
  const current = get();
  apply(current);
  if (current === "system" && window.matchMedia) {
    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => apply("system"));
  }
  return { set, get, apply };
})();