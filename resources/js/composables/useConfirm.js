import { ref } from 'vue';

const isOpen  = ref(false);
const options = ref({});
let resolvePromise = null;

/**
 * A single, app-wide confirmation dialog — replaces the browser's native
 * confirm() so destructive actions look like the rest of the app instead
 * of a jarring OS popup. Await the promise to know what the user chose.
 *
 *   const ok = await confirm({ title: 'Delete this?', message: '...' });
 *   if (!ok) return;
 */
export function useConfirm() {
  function confirm(opts = {}) {
    options.value = {
      title:       'Are you sure?',
      message:     '',
      confirmText: 'Confirm',
      cancelText:  'Cancel',
      danger:      true,
      ...opts,
    };
    isOpen.value = true;
    return new Promise((resolve) => { resolvePromise = resolve; });
  }

  function handleConfirm() {
    isOpen.value = false;
    resolvePromise?.(true);
  }

  function handleCancel() {
    isOpen.value = false;
    resolvePromise?.(false);
  }

  return { isOpen, options, confirm, handleConfirm, handleCancel };
}
