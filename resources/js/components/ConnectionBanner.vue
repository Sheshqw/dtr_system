<template>
  <Transition name="cb">
    <div v-if="view" class="cb-wrap" role="status" aria-live="polite">
      <div class="cb" :class="`cb--${view.tone}`">
        <component :is="view.icon" class="cb-icon" :class="{ 'cb-spin': view.spin }" />
        <div class="cb-text">
          <span class="cb-title">{{ view.title }}</span>
          <span v-if="view.detail" class="cb-detail">{{ view.detail }}</span>
        </div>
        <button
          v-if="view.retry"
          type="button"
          class="cb-retry"
          :disabled="state.checking"
          @click="checkNow"
        >
          {{ state.checking ? 'Checking…' : 'Retry now' }}
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue';
import { useConnectionStatus } from '@/composables/useConnectionStatus';
import {
  CheckCircle2 as CheckCircle2Icon,
  Loader2      as Loader2Icon,
  ServerCrash  as ServerCrashIcon,
  Wrench       as WrenchIcon,
  WifiOff      as WifiOffIcon,
} from 'lucide-vue-next';

const { state, checkNow } = useConnectionStatus();

const view = computed(() => {
  switch (state.status) {
    case 'offline':
      return {
        tone: 'warn', icon: WifiOffIcon,
        title: "You're offline",
        detail: "Check your internet connection. Changes can't be saved until you reconnect.",
      };
    case 'unreachable':
      return {
        tone: 'warn',
        icon: state.checking ? Loader2Icon : ServerCrashIcon,
        spin: state.checking,
        title: "Can't reach the server",
        detail: 'Reconnecting automatically…',
        retry: true,
      };
    case 'maintenance':
      return {
        tone: 'info', icon: WrenchIcon,
        title: 'System under maintenance',
        detail: "It will be back shortly — this page reconnects on its own.",
        retry: true,
      };
    case 'restored':
      return { tone: 'ok', icon: CheckCircle2Icon, title: 'Back online' };
    default:
      return null;
  }
});
</script>

<style scoped>
.cb-wrap {
  position: fixed;
  top: 12px;
  left: 0;
  right: 0;
  z-index: 9999;
  display: flex;
  justify-content: center;
  padding: 0 16px;
  pointer-events: none;
}
.cb {
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: 560px;
  padding: 10px 12px 10px 14px;
  border-radius: 12px;
  border: 1px solid transparent;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
  pointer-events: auto;
}
.cb--warn { color: #7C2D12; background: #FFF7ED; border-color: #FDBA74; }
.cb--info { color: #1E3A8A; background: #EFF6FF; border-color: #93C5FD; }
.cb--ok   { color: #14532D; background: #F0FDF4; border-color: #86EFAC; }
[data-theme="dark"] .cb--warn { color: #FED7AA; background: #3B2414; border-color: rgba(253, 186, 116, 0.35); }
[data-theme="dark"] .cb--info { color: #BFDBFE; background: #172554; border-color: rgba(147, 197, 253, 0.35); }
[data-theme="dark"] .cb--ok   { color: #BBF7D0; background: #12301F; border-color: rgba(134, 239, 172, 0.35); }

.cb-icon { width: 18px; height: 18px; flex-shrink: 0; }
.cb-spin { animation: cb-spin 1s linear infinite; }
@keyframes cb-spin { to { transform: rotate(360deg); } }

.cb-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.35; }
.cb-title  { font-size: 13.5px; font-weight: 700; }
.cb-detail { font-size: 12px; opacity: 0.85; }

.cb-retry {
  flex-shrink: 0;
  margin-left: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
  color: inherit;
  background: color-mix(in srgb, currentColor 10%, transparent);
  border: 1px solid color-mix(in srgb, currentColor 25%, transparent);
  transition: background-color 0.15s ease;
}
.cb-retry:hover:not(:disabled) { background: color-mix(in srgb, currentColor 18%, transparent); }
.cb-retry:disabled { opacity: 0.6; cursor: default; }

.cb-enter-active, .cb-leave-active { transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1); }
.cb-enter-from, .cb-leave-to { opacity: 0; transform: translateY(-10px); }

@media (max-width: 480px) {
  .cb-wrap { top: 8px; }
  .cb { width: 100%; }
}
</style>
