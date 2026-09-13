import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
  // `options.actionLabel` + `options.onAction` render an inline button (e.g.
  // "Undo") in the toast — for a reversible action, this replaces asking
  // "are you sure?" up front: do it immediately, offer a few seconds to
  // change their mind instead of interrupting every time.
  function toast(message, type = 'success', duration = 4000, options = {}) {
    const id = ++nextId;
    toasts.value.push({
      id, message, type, duration, visible: true,
      actionLabel: options.actionLabel ?? null,
      onAction:    options.onAction ?? null,
    });
    setTimeout(() => dismiss(id), duration);
    return id;
  }

  function dismiss(id) {
    const item = toasts.value.find(t => t.id === id);
    if (item) item.visible = false;
    setTimeout(() => {
      const idx = toasts.value.findIndex(t => t.id === id);
      if (idx !== -1) toasts.value.splice(idx, 1);
    }, 300);
  }

  return { toasts, toast, dismiss };
}
