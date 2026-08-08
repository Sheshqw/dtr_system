<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide ring-1"
    :class="[config.bg, config.text, config.ring]"
  >
    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="config.dot"></span>
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
  status:    { type: String, required: true },
  corrected: { type: Boolean, default: false },
});

const statusMap = {
  present:  { label: 'Present',   dot: 'bg-emerald-500', bg: 'bg-emerald-50',  text: 'text-emerald-700', ring: 'ring-emerald-200' },
  late:     { label: 'Late',      dot: 'bg-amber-500',   bg: 'bg-amber-50',    text: 'text-amber-700',   ring: 'ring-amber-200'   },
  absent:   { label: 'Absent',    dot: 'bg-red-500',     bg: 'bg-red-50',      text: 'text-red-700',     ring: 'ring-red-200'     },
  on_leave: { label: 'On Leave',  dot: 'bg-indigo-500',  bg: 'bg-indigo-50',   text: 'text-indigo-700',  ring: 'ring-indigo-200'  },
  holiday:  { label: 'Holiday',   dot: 'bg-violet-500',  bg: 'bg-violet-50',   text: 'text-violet-700',  ring: 'ring-violet-200'  },
  weekend:  { label: 'Weekend',   dot: 'bg-slate-400',   bg: 'bg-slate-50',    text: 'text-slate-500',   ring: 'ring-slate-200'   },
};

const config = computed(() =>
  statusMap[props.status] ?? { label: props.status, dot: 'bg-slate-400', bg: 'bg-slate-50', text: 'text-slate-600', ring: 'ring-slate-200' }
);
</script>
