<template>
  <span :class="['inline-flex px-2 py-0.5 text-xs font-medium rounded-md font-mono', cfg.bg, cfg.text]">
    {{ type }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ type: String });

const map = {
  SL:   { bg: 'bg-blue-50',    text: 'text-blue-700'   },
  VL:   { bg: 'bg-teal-50',    text: 'text-teal-700'   },
  EL:   { bg: 'bg-orange-50',  text: 'text-orange-700' },
  LWOP: { bg: 'bg-red-50',     text: 'text-red-700'    },
  OB:   { bg: 'bg-violet-50',  text: 'text-violet-700' },
  SPL:  { bg: 'bg-pink-50',    text: 'text-pink-700'   },
  ML:   { bg: 'bg-rose-50',    text: 'text-rose-700'   },
  PL:   { bg: 'bg-indigo-50',  text: 'text-indigo-700' },
};

const cfg = computed(() => map[props.type] ?? { bg: 'bg-slate-50', text: 'text-slate-600' });
</script>
