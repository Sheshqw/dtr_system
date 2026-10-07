(function () {
  // Only an explicit, saved user choice applies dark mode here — the
  // store's own init() (resources/js/stores/theme.js) deliberately stopped
  // following the OS's prefers-color-scheme, since this app's dark theme
  // never fully covered every page and auto-following the device's dark
  // mode produced a half-themed, broken-looking result. This script runs
  // before Vue mounts purely to avoid a flash of the wrong theme, so it
  // must mirror that same policy exactly.
  var mode = localStorage.getItem('dtr_theme_mode');
  if (mode === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
