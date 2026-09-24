<template>
  <Teleport to="body">
    <Transition name="cd-fade">
      <div v-if="isOpen" class="cd-overlay" @click.self="handleCancel">
        <Transition name="cd-pop" appear>
          <div v-if="isOpen" class="cd-panel" role="alertdialog" aria-modal="true" :aria-label="options.title">
            <div class="cd-body">
              <div class="cd-icon" :style="{ background: options.danger ? '#FEF2F2' : '#FFFBEB' }">
                <AlertTriangleIcon class="w-5 h-5" :style="{ color: options.danger ? '#DC2626' : '#D97706' }" />
              </div>
              <div class="cd-text">
                <h3 class="cd-title">{{ options.title }}</h3>
                <p v-if="options.message" class="cd-message">{{ options.message }}</p>
              </div>
            </div>
            <div class="cd-actions">
              <button class="cd-btn cd-btn--cancel" @click="handleCancel">{{ options.cancelText }}</button>
              <button
                class="cd-btn cd-btn--confirm"
                :style="{ background: options.danger ? '#DC2626' : '#2E4A8C' }"
                @click="handleConfirm"
              >{{ options.confirmText }}</button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { AlertTriangle as AlertTriangleIcon } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';

const { isOpen, options, handleConfirm, handleCancel } = useConfirm();
</script>

<style scoped>
.cd-overlay {
  position: fixed; inset: 0; z-index: 110;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.cd-panel {
  width: 100%; max-width: 380px;
  background: #fff; border-radius: 16px;
  box-shadow: 0 24px 60px rgba(8,14,80,0.30), 0 4px 16px rgba(8,14,80,0.12);
  padding: 22px 22px 18px;
}
.cd-body { display: flex; align-items: flex-start; gap: 14px; }
.cd-icon {
  width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.cd-text { flex: 1; padding-top: 2px; }
.cd-title { font-size: 15px; font-weight: 700; color: #0F172A; }
.cd-message { font-size: 13px; color: #64748B; margin-top: 4px; line-height: 1.45; }

.cd-actions { display: flex; gap: 10px; margin-top: 18px; }
.cd-btn {
  flex: 1; padding: 9px 0; border-radius: 10px;
  font-size: 13.5px; font-weight: 600;
  transition: filter 0.12s;
}
.cd-btn--cancel { background: #F1F5F9; color: #374151; }
.cd-btn--cancel:hover { filter: brightness(0.97); }
.cd-btn--confirm { color: #fff; }
.cd-btn--confirm:hover { filter: brightness(1.08); }

.cd-fade-enter-active, .cd-fade-leave-active { transition: opacity 0.15s ease; }
.cd-fade-enter-from, .cd-fade-leave-to { opacity: 0; }
.cd-pop-enter-active { transition: opacity 0.15s ease, transform 0.15s cubic-bezier(0.22,1,0.36,1); }
.cd-pop-enter-from { opacity: 0; transform: translateY(-6px) scale(0.97); }
</style>
