import { ref, computed, unref } from 'vue';

/**
 * Click-to-sort for a table. `accessors` maps a column key to a function
 * that pulls the comparable value out of a row — badges/labels don't sort
 * directly, so callers reduce them to a primitive first (e.g. status -> 0/1).
 *
 *   const { sortKey, sortDir, sort, sorted } = useSortable(filtered, {
 *     name: (e) => e.name?.toLowerCase() ?? '',
 *   });
 */
export function useSortable(list, accessors, initialKey = null, initialDir = 'asc') {
  const sortKey = ref(initialKey);
  const sortDir = ref(initialDir);

  function sort(key) {
    if (!accessors[key]) return;
    if (sortKey.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
      sortKey.value = key;
      sortDir.value = 'asc';
    }
  }

  const sorted = computed(() => {
    const items = unref(list);
    const get = accessors[sortKey.value];
    if (!get) return items;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...items].sort((a, b) => {
      const av = get(a);
      const bv = get(b);
      if (av == null && bv == null) return 0;
      if (av == null) return 1;
      if (bv == null) return -1;
      if (typeof av === 'string') return av.localeCompare(bv) * dir;
      return (av > bv ? 1 : av < bv ? -1 : 0) * dir;
    });
  });

  return { sortKey, sortDir, sort, sorted };
}
