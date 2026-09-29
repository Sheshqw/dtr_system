import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  const mode   = ref('light'); // 'light' | 'dark' — 'system' was removed: on this app it always rendered identically to 'dark', just adding a confusing third option
  const isDark = ref(false);

  function apply() {
    const dark = mode.value === 'dark';
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    isDark.value = dark;
  }

  function setMode(m) {
    mode.value = m;
    localStorage.setItem('dtr_theme_mode', m);
    apply();
  }

  function init() {
    const saved = localStorage.getItem('dtr_theme_mode');
    if (saved && saved !== 'system') {
      mode.value = saved;
    } else if (saved === 'system') {
      // 'system' option was removed — fall back to light rather than
      // leaving a now-invalid mode saved.
      mode.value = 'light';
      localStorage.setItem('dtr_theme_mode', 'light');
    } else {
      // Migrate old toggle key ('dark'|'light') → new mode key
      const legacy = localStorage.getItem('dtr_theme');
      mode.value = legacy === 'dark' ? 'dark' : 'light';
    }
    apply();
  }

  // Kept for any existing callers (DashboardView theme watcher, etc.)
  function toggle() {
    setMode(isDark.value ? 'light' : 'dark');
  }

  return { mode, isDark, setMode, toggle, init };
});
