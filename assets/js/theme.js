// Tema global (Bootstrap 5.3+): light | dark | system
// - Persiste em localStorage e cookie (sa_theme)
// - Aplica em <html> e <body>
// - Oferece toggle() e set()

window.SATheme = (function () {
  const KEY = 'sa_theme';

  function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : null;
  }

  function effectiveTheme(theme) {
    if (theme === 'system') {
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
    }
    return theme;
  }

  function apply(theme) {
    const eff = effectiveTheme(theme);
    document.documentElement.setAttribute('data-bs-theme', eff);
    if (document.body) document.body.setAttribute('data-bs-theme', eff);
    document.documentElement.classList.toggle('theme-dark', eff === 'dark');
    updateIcon();
  }

  function set(theme) {
    localStorage.setItem(KEY, theme);
    document.cookie = `${KEY}=${encodeURIComponent(theme)}; path=/; max-age=31536000`;
    apply(theme);

    // se existir um marcador em tela (página Configurações)
    const el = document.getElementById('saThemeCurrent');
    if (el) el.textContent = theme;
  }

  function get() {
    return localStorage.getItem(KEY) || getCookie(KEY) || 'light';
  }

  function toggle() {
    const current = get();
    // alterna apenas entre dark/light (mantém system só quando escolhido explicitamente)
    if (current === 'dark') return set('light');
    return set('dark');
  }

  function updateIcon() {
    const icon = document.getElementById('themeIcon');
    if (!icon) return;

    const eff = effectiveTheme(get());
    icon.className = eff === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  // inicializa
  const current = get();
  apply(current);

  // se for "system", reage a mudança do sistema operacional
  if (current === 'system' && window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => apply('system'));
  }

  // garante que o <body> exista antes (em alguns carregamentos)
  document.addEventListener('DOMContentLoaded', () => apply(get()));

  return { set, get, toggle, apply };
})();
