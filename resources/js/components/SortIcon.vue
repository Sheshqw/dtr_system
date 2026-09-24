<template>
  <ChevronUpIcon   v-if="state === 'asc'"  class="w-3 h-3 text-[#2E4A8C]" />
  <ChevronDownIcon v-else-if="state === 'desc'" class="w-3 h-3 text-[#2E4A8C]" />
  <ChevronsUpDownIcon v-else class="w-3 h-3 text-[#94A3B8]" />
</template>

<script setup>
import {
  ChevronUp as ChevronUpIcon,
  ChevronDown as ChevronDownIcon,
  ChevronsUpDown as ChevronsUpDownIcon,
} from 'lucide-vue-next';

defineProps({
  // 'asc' | 'desc' | null — null means this column isn't the active sort.
  state: { type: String, default: null },
});
</script>
