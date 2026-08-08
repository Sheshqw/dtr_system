<template>
  <span :class="['inline-flex px-2 py-0.5 text-xs font-medium rounded-md', cfg.bg, cfg.text]">
    {{ cfg.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ role: String });

const map = {
  admin:           { label: 'Admin',        bg: 'bg-blue-50',   text: 'text-blue-700'   },
  hr_officer:      { label: 'HR Officer',   bg: 'bg-violet-50', text: 'text-violet-700' },
  department_head: { label: 'Dept. Head',   bg: 'bg-amber-50',  text: 'text-amber-700'  },
  employee:        { label: 'Faculty / Staff', bg: 'bg-slate-50',  text: 'text-slate-600'  },
};

const cfg = computed(() => map[props.role] ?? { label: props.role, bg: 'bg-slate-50', text: 'text-slate-600' });
</script>
