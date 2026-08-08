<template>
  <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full" :class="config.class">
    <component :is="config.icon" class="w-3 h-3" />
    {{ config.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import {
  GraduationCap as GraduationCapIcon,
  Briefcase     as BriefcaseIcon,
  Shield        as ShieldIcon,
  Sparkles      as SparklesIcon,
  Wrench        as WrenchIcon,
  FlaskConical  as FlaskConicalIcon,
  Car           as CarIcon,
  MoreHorizontal as MoreHorizontalIcon,
} from 'lucide-vue-next';

const props = defineProps({ type: { type: String, required: true } });

const map = {
  faculty:        { label: 'Faculty',        icon: GraduationCapIcon,  class: 'bg-indigo-100 text-indigo-700'  },
  administrative: { label: 'Administrative', icon: BriefcaseIcon,      class: 'bg-blue-100 text-blue-700'     },
  security:       { label: 'Security',       icon: ShieldIcon,         class: 'bg-slate-100 text-slate-700'   },
  custodial:      { label: 'Custodial',      icon: SparklesIcon,       class: 'bg-teal-100 text-teal-700'     },
  maintenance:    { label: 'Maintenance',    icon: WrenchIcon,         class: 'bg-orange-100 text-orange-700' },
  laboratory:     { label: 'Laboratory',     icon: FlaskConicalIcon,   class: 'bg-violet-100 text-violet-700' },
  driver:         { label: 'Driver',         icon: CarIcon,            class: 'bg-amber-100 text-amber-700'   },
  other:          { label: 'Other',          icon: MoreHorizontalIcon, class: 'bg-gray-100 text-gray-600'     },
};

const config = computed(() => map[props.type] ?? map.other);
</script>
