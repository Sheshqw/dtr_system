import { useConfirm } from '@/composables/useConfirm';

/**
 * Protects a modal form from silently discarding typed data.
 *
 *   const guard = useUnsavedGuard();
 *   // when the form opens, after its fields are populated:
 *   guard.snapshot(form);
 *   // wherever the modal would normally just close:
 *   guard.close(form, () => { modal.open = false; });
 */
export function useUnsavedGuard() {
  const { confirm } = useConfirm();
  let baseline = null;

  function snapshot(formRef) {
    baseline = JSON.stringify(formRef);
  }

  function isDirty(formRef) {
    return baseline !== null && JSON.stringify(formRef) !== baseline;
  }

  async function close(formRef, closeFn) {
    if (isDirty(formRef)) {
      const ok = await confirm({
        title:       'Discard changes?',
        message:     'You have unsaved changes that will be lost if you leave.',
        confirmText: 'Discard',
        cancelText:  'Keep editing',
        danger:      true,
      });
      if (!ok) return;
    }
    baseline = null;
    closeFn();
  }

  return { snapshot, isDirty, close };
}
