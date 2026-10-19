<template>
  <MyTodayCard
    :row="todayRow"
    :shift="summary?.shift ?? null"
    :pending-print-requests="pendingPrintRequests"
    :hero-gradient="heroGradient"
  />
  <MyAttendanceInsights ref="insightsRef" :summary="summary" :hero-gradient="heroGradient" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import MyTodayCard          from '@/components/MyTodayCard.vue';
import MyAttendanceInsights from '@/components/MyAttendanceInsights.vue';

/**
 * The Faculty / Staff dashboard: today's punches plus personal analytics.
 * Loads the summary (shift, tracking start, trend, streaks, weekday
 * pattern) once and shares it with both cards.
 */
defineProps({
  todayRow:             { type: Object, default: null },
  pendingPrintRequests: { type: Number, default: 0 },
  heroGradient:         { type: String, default: '#1B2A5E' },
});

const summary     = ref(null);
const insightsRef = ref(null);

async function loadSummary() {
  try {
    const res = await axios.get('/api/me/attendance-summary', { params: { months: 6 } });
    summary.value = res.data;
  } catch {
    // The cards still work from today's row and the month view without it.
  }
}

// Called by the dashboard's Refresh button and live punch updates.
async function load() {
  await Promise.all([loadSummary(), insightsRef.value?.load()]);
}

onMounted(loadSummary);
defineExpose({ load });
</script>
