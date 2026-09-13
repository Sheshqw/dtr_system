<template>
  <Teleport to="body">
    <div class="fixed bottom-5 right-5 z-[9999] flex flex-col-reverse gap-2.5 pointer-events-none" style="width: 380px;">
      <TransitionGroup name="toast" tag="div" class="flex flex-col-reverse gap-2.5">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="flex items-start gap-3 pr-4 py-3.5 pl-0 rounded-xl shadow-xl bg-white pointer-events-auto cursor-pointer select-none overflow-hidden border border-white/60 relative"
          :style="toastStyle(t.type)"
          @click="dismiss(t.id)"
        >
          <!-- Left colored accent bar -->
          <div class="w-1 self-stretch rounded-r flex-shrink-0" :class="cfg(t.type).bar"></div>

          <!-- Icon -->
          <component
            :is="cfg(t.type).icon"
            class="w-4.5 h-4.5 flex-shrink-0 mt-0.5"
            :class="cfg(t.type).icon_color"
          />

          <!-- Message -->
          <p class="text-sm font-medium leading-snug flex-1 text-[#0C1445]">{{ t.message }}</p>

          <!-- Action (e.g. "Undo") -->
          <button
            v-if="t.actionLabel"
            @click.stop="runAction(t)"
            class="flex-shrink-0 text-xs font-bold uppercase tracking-wide px-2 py-1 -my-1 rounded-md transition-colors mt-0.5"
            :class="cfg(t.type).action_class"
          >{{ t.actionLabel }}</button>

          <!-- Close -->
          <button class="flex-shrink-0 text-[#8B9EC5] hover:text-[#0C1445] transition-colors mt-0.5">
            <XIcon class="w-4 h-4" />
          </button>

          <!-- Progress drain bar -->
          <div
            class="toast-progress"
            :class="cfg(t.type).bar"
            :style="{ '--dur': (t.duration ?? 4000) + 'ms' }"
          ></div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '@/composables/useToast';
import {
  CheckCircle2  as CheckCircle2Icon,
  AlertCircle   as AlertCircleIcon,
  AlertTriangle as AlertTriangleIcon,
  Info          as InfoIcon,
  X             as XIcon,
} from 'lucide-vue-next';

const { toasts, dismiss } = useToast();

const configs = {
  success: {
    bar:          'bg-emerald-500',
    icon:         CheckCircle2Icon,
    icon_color:   'text-emerald-500',
    action_class: 'text-emerald-600 hover:bg-emerald-50',
    shadow:       '0 8px 32px rgba(5,150,105,0.18), 0 2px 8px rgba(0,0,0,0.06)',
  },
  error: {
    bar:          'bg-red-500',
    icon:         AlertCircleIcon,
    icon_color:   'text-red-500',
    action_class: 'text-red-600 hover:bg-red-50',
    shadow:       '0 8px 32px rgba(220,38,38,0.18), 0 2px 8px rgba(0,0,0,0.06)',
  },
  warning: {
    bar:          'bg-amber-500',
    icon:         AlertTriangleIcon,
    icon_color:   'text-amber-500',
    action_class: 'text-amber-700 hover:bg-amber-50',
    shadow:       '0 8px 32px rgba(217,119,6,0.18), 0 2px 8px rgba(0,0,0,0.06)',
  },
  info: {
    bar:          'bg-blue-500',
    icon:         InfoIcon,
    icon_color:   'text-blue-500',
    action_class: 'text-blue-600 hover:bg-blue-50',
    shadow:       '0 8px 32px rgba(59,130,246,0.18), 0 2px 8px rgba(0,0,0,0.06)',
  },
};

function cfg(type) { return configs[type] ?? configs.info; }

function toastStyle(type) {
  return { boxShadow: cfg(type).shadow };
}

function runAction(t) {
  t.onAction?.();
  dismiss(t.id);
}
</script>

<style scoped>
/* Slide up from bottom-right */
.toast-enter-active { transition: all 0.34s cubic-bezier(0.34, 1.56, 0.64, 1); }
.toast-leave-active { transition: all 0.22s cubic-bezier(0.4, 0, 1, 1); }
.toast-enter-from   { opacity: 0; transform: translateY(24px) scale(0.93); }
.toast-leave-to     { opacity: 0; transform: translateY(12px) scale(0.93); }
.toast-move         { transition: transform 0.28s ease; }

/* Progress drain bar */
.toast-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  border-radius: 0;
  opacity: 0.55;
  transform-origin: left center;
  animation: toastDrain var(--dur, 4000ms) linear forwards;
}
@keyframes toastDrain {
  from { transform: scaleX(1); }
  to   { transform: scaleX(0); }
}
</style>
