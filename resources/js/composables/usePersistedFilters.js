import { watch } from 'vue';

/**
 * Remembers a reactive filters object in localStorage across visits.
 * Keys listed in `exclude` (e.g. free-text search, or a value seeded from
 * a route query for deep-linking) are read from and written to the store
 * as normal but never loaded on mount, so they don't fight a fresh intent.
 */
export function usePersistedFilters(key, filters, exclude = []) {
  try {
    const saved = JSON.parse(localStorage.getItem(key) ?? 'null');
    if (saved && typeof saved === 'object') {
      for (const k of Object.keys(saved)) {
        if (!exclude.includes(k) && k in filters) filters[k] = saved[k];
      }
    }
  } catch {
    // Corrupt or inaccessible storage — just start from defaults.
  }

  watch(filters, () => {
    try {
      localStorage.setItem(key, JSON.stringify(filters));
    } catch {
      // Storage unavailable (private mode, quota) — filters still work in-session.
    }
  }, { deep: true });
}
