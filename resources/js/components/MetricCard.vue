<template>
  <div
    class="bg-white rounded-xl overflow-hidden transition-shadow duration-200 hover:shadow-md"
    style="border: 1px solid #E2E8F0;"
    role="figure"
    :aria-label="`${label}: ${value ?? 0} employees`"
  >
    <!-- Thin top accent line -->
    <div class="h-0.5 w-full" :style="{ background: palette.color }"></div>

    <div class="p-5">
      <!-- Label + percentage -->
      <div class="flex items-center justify-between mb-3">
        <p class="text-xs font-semibold uppercase tracking-widest" style="color: #94A3B8;">
          {{ label }}
        </p>
        <span
          v-if="total > 0"
          class="text-xs font-bold px-2 py-0.5 rounded-md"
          :style="{ background: palette.bgLight, color: palette.color }"
        >{{ pct }}%</span>
      </div>

      <!-- Number + icon -->
      <div class="flex items-end justify-between">
        <p class="text-4xl font-extrabold leading-none tracking-tight" style="color: #0F172A;">
          {{ value ?? 0 }}
        </p>
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
          :style="{ background: palette.bgLight }"
          aria-hidden="true"
        >
          <component :is="iconComponent" class="w-4 h-4" :style="{ color: palette.color }" />
        </div>
      </div>

      <p class="text-xs mt-1.5" style="color: #94A3B8;">employees today</p>

      <!-- Progress bar -->
      <div class="mt-4 h-1 rounded-full" style="background: #F1F5F9;">
        <div
          class="h-1 rounded-full transition-all duration-700"
          :style="{ width: pct + '%', background: palette.color }"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  CheckCircle2 as CheckCircle2Icon,
  Clock        as ClockIcon,
  XCircle      as XCircleIcon,
  Calendar     as CalendarIcon,
} from 'lucide-vue-next';

const props = defineProps({
  label: String,
  value: Number,
  icon:  String,
  total: { type: Number, default: 0 },
});

const palettes = {
  'check-circle': { color: '#059669', bgLight: '#ECFDF5' },
  'clock':        { color: '#D97706', bgLight: '#FFFBEB' },
  'x-circle':     { color: '#DC2626', bgLight: '#FEF2F2' },
  'calendar':     { color: '#4338CA', bgLight: '#EEF2FF' },
};

const palette = computed(() => palettes[props.icon] ?? palettes['calendar']);

const pct = computed(() =>
  props.total > 0 ? Math.round(((props.value ?? 0) / props.total) * 100) : 0
);

const iconComponent = computed(() => {
  if (props.icon === 'check-circle') return CheckCircle2Icon;
  if (props.icon === 'clock')        return ClockIcon;
  if (props.icon === 'x-circle')     return XCircleIcon;
  return CalendarIcon;
});
</script>
