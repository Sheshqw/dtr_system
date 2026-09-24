import { defineStore } from 'pinia';
import { ref } from 'vue';

// Scoped to the whole document (not just #app) so it also reaches modals
// teleported to <body> (ConfirmDialog, CommandPalette, change-password /
// sessions modals, etc.) — those live outside the Vue mount point, so a
// scale applied only to #app would silently skip them.
const SCALES = { sm: 0.925, md: 1, lg: 1.125 };

export const useTextSizeStore = defineStore('textSize', () => {
  const level = ref('md'); // 'sm' | 'md' | 'lg'

  function apply() {
    document.documentElement.style.zoom = String(SCALES[level.value] ?? 1);
  }

  function setLevel(l) {
    if (!SCALES[l]) return;
    level.value = l;
    localStorage.setItem('dtr_text_size', l);
    apply();
  }

  function init() {
    const saved = localStorage.getItem('dtr_text_size');
    if (saved && SCALES[saved]) level.value = saved;
    apply();
  }

  return { level, setLevel, init };
});
