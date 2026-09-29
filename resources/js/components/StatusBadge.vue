<template>
  <span
    class="sb-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide ring-1"
    :class="[config.bg, config.text, config.ring]"
    :style="{ boxShadow: `0 1px 3px ${config.glow}, inset 0 1px 0 rgba(255,255,255,0.35)` }"
  >
    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 sb-dot" :class="config.dot" :style="{ boxShadow: `0 0 0 3px ${config.glow}` }"></span>
    {{ config.label }}
    <span
      v-if="corrected"
      class="text-[10px] font-normal opacity-60 -ml-0.5"
      title="Manually corrected"
    >✎</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status:      { type: String, required: true },
  corrected:   { type: Boolean, default: false },
  holidayName: { type: String, default: null },
});

const statusMap = {
  present:  { label: 'Present',   dot: 'bg-emerald-500', bg: 'bg-emerald-50',  text: 'text-emerald-700', ring: 'ring-emerald-200', glow: 'rgba(16,185,129,0.16)'  },
  late:     { label: 'Late',      dot: 'bg-amber-500',   bg: 'bg-amber-50',    text: 'text-amber-700',   ring: 'ring-amber-200',   glow: 'rgba(217,119,6,0.16)'   },
  absent:   { label: 'Absent',    dot: 'bg-red-500',     bg: 'bg-red-50',      text: 'text-red-700',     ring: 'ring-red-200',     glow: 'rgba(220,38,38,0.16)'   },
  on_leave: { label: 'On Leave',  dot: 'bg-indigo-500',  bg: 'bg-indigo-50',   text: 'text-indigo-700',  ring: 'ring-indigo-200',  glow: 'rgba(99,102,241,0.16)'  },
  holiday:  { label: 'Holiday',   dot: 'bg-violet-500',  bg: 'bg-violet-50',   text: 'text-violet-700',  ring: 'ring-violet-200',  glow: 'rgba(139,92,246,0.16)'  },
  weekend:  { label: 'Weekend',   dot: 'bg-slate-400',   bg: 'bg-slate-50',    text: 'text-slate-500',   ring: 'ring-slate-200',   glow: 'rgba(148,163,184,0.16)' },
};

const config = computed(() => {
  const base = statusMap[props.status] ?? { label: props.status, dot: 'bg-slate-400', bg: 'bg-slate-50', text: 'text-slate-600', ring: 'ring-slate-200', glow: 'rgba(148,163,184,0.16)' };
  if (props.status === 'holiday' && props.holidayName) {
    return { ...base, label: props.holidayName };
  }
  return base;
});
</script>

<style scoped>
.sb-pill { transition: transform 0.15s ease, box-shadow 0.15s ease; }
.sb-pill:hover { transform: translateY(-1px); }
</style>
