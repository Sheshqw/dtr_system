import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
  function toast(message, type = 'success', duration = 4000) {
    const id = ++nextId;
    toasts.value.push({ id, message, type, duration, visible: true });
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
