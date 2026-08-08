import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  const mode   = ref('system'); // 'light' | 'dark' | 'system'
  const isDark = ref(false);

  const mq = window.matchMedia('(prefers-color-scheme: dark)');

  function apply() {
    const dark = mode.value === 'dark' || (mode.value === 'system' && mq.matches);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    isDark.value = dark;
  }

  function setMode(m) {
    mode.value = m;
    localStorage.setItem('dtr_theme_mode', m);
    apply();
  }

  // When OS preference changes and user is in System mode, re-apply
  mq.addEventListener('change', () => {
    if (mode.value === 'system') apply();
  });

  function init() {
    const saved = localStorage.getItem('dtr_theme_mode');
    if (saved) {
      mode.value = saved;
    } else {
      // Migrate old toggle key ('dark'|'light') → new three-mode key
      const legacy = localStorage.getItem('dtr_theme');
      mode.value = legacy ? legacy : 'system';
    }
    apply();
  }

  // Kept for any existing callers (DashboardView theme watcher, etc.)
  function toggle() {
    setMode(isDark.value ? 'light' : 'dark');
  }

  return { mode, isDark, setMode, toggle, init };
});
