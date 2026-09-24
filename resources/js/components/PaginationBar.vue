<template>
  <div v-if="lastPage > 1" class="flex items-center justify-between py-3 px-1">
    <p class="text-xs" style="color: #64748B;">
      Showing <span class="font-semibold">{{ from }}–{{ to }}</span> of
      <span class="font-semibold">{{ total }}</span> records
    </p>

    <div class="flex items-center gap-1">
      <!-- Prev -->
      <button
        @click="$emit('go', currentPage - 1)"
        :disabled="currentPage === 1"
        class="w-8 h-8 flex items-center justify-center rounded-md transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        style="border: 1px solid #E5E7EB; color: #374151;"
        @mouseover="e => !$attrs.disabled && (e.currentTarget.style.background='#F0F4FF')"
        @mouseout="e => (e.currentTarget.style.background='')"
        aria-label="Previous page"
      >
        <ChevronLeftIcon class="w-4 h-4" />
      </button>

      <!-- Page numbers -->
      <template v-for="p in visiblePages" :key="p">
        <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-xs" style="color:#6B7A94;">…</span>
        <button
          v-else
          @click="$emit('go', p)"
          class="w-8 h-8 flex items-center justify-center rounded-md text-sm font-medium transition-colors"
          :style="p === currentPage
            ? 'background:#2E4A8C;color:#fff;'
            : 'border:1px solid #E5E7EB;color:#374151;'"
          @mouseover="e => p !== currentPage && (e.currentTarget.style.background='#F0F4FF')"
          @mouseout="e => p !== currentPage && (e.currentTarget.style.background='')"
        >{{ p }}</button>
      </template>

      <!-- Next -->
      <button
        @click="$emit('go', currentPage + 1)"
        :disabled="currentPage === lastPage"
        class="w-8 h-8 flex items-center justify-center rounded-md transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        style="border: 1px solid #E5E7EB; color: #374151;"
        @mouseover="e => !$attrs.disabled && (e.currentTarget.style.background='#F0F4FF')"
        @mouseout="e => (e.currentTarget.style.background='')"
        aria-label="Next page"
      >
        <ChevronRightIcon class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon } from 'lucide-vue-next';

const props = defineProps({
  currentPage: { type: Number, required: true },
  lastPage:    { type: Number, required: true },
  total:       { type: Number, default: 0 },
  from:        { type: Number, default: 0 },
  to:          { type: Number, default: 0 },
});

defineEmits(['go']);

const visiblePages = computed(() => {
  const { currentPage: c, lastPage: l } = props;
  if (l <= 7) return Array.from({ length: l }, (_, i) => i + 1);

  const pages = [];
  pages.push(1);
  if (c > 3) pages.push('...');
  for (let p = Math.max(2, c - 1); p <= Math.min(l - 1, c + 1); p++) pages.push(p);
  if (c < l - 2) pages.push('...');
  pages.push(l);
  return pages;
});
</script>
