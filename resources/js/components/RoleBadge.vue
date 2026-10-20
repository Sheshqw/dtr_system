<template>
  <span
    :class="['rb-pill inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-md', cfg.bg, cfg.text]"
    :style="cfg.gold ? {} : { boxShadow: `inset 0 1px 0 rgba(255,255,255,0.35)` }"
  >
    <span v-if="cfg.gold" class="rb-crown">★</span>
    {{ cfg.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ role: String });

const map = {
  super_admin:     { label: 'Super Admin',     bg: 'rb-gold',      text: '',                gold: true },
  hr_officer:      { label: 'HR Officer',      bg: 'bg-violet-50', text: 'text-violet-700' },
  employee:        { label: 'Faculty / Staff', bg: 'bg-slate-50',  text: 'text-slate-600'  },
};

const ROLE_LABELS = {
  super_admin: 'Super Admin', hr_officer: 'HR Officer', employee: 'Faculty / Staff',
};

const cfg = computed(() =>
  map[props.role] ?? {
    label: ROLE_LABELS[props.role] ?? (props.role?.replace(/_/g, ' ') ?? '—'),
    bg: 'bg-slate-50', text: 'text-slate-600',
  }
);
</script>

<style scoped>
.rb-pill { transition: transform 0.15s ease; }
.rb-pill:hover { transform: translateY(-1px); }
.rb-crown { font-size: 9px; line-height: 1; }

/* Super Admin gets the brand gold treatment, not a generic tint —
   it's the highest-privilege role and should read as distinct at a glance. */
.rb-gold {
  background: linear-gradient(135deg, rgba(201, 162, 39,0.16), rgba(201, 162, 39,0.08));
  color: #B8911F;
  border: 1px solid rgba(201, 162, 39,0.35);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
}
[data-theme="dark"] .rb-gold {
  color: #E8C040;
  background: linear-gradient(135deg, rgba(201, 162, 39,0.22), rgba(201, 162, 39,0.1));
  border-color: rgba(201, 162, 39,0.4);
  box-shadow: none;
}
</style>
