<template>
  <section class="mi" :class="{ 'mi--loading': loading }" aria-labelledby="mi-title">

    <!-- ── Header: title + month switcher ─────────────────────────── -->
    <header class="mi-hero" :style="{ background: heroGradient }">
      <div class="mi-hero-left">
        <div class="mi-hero-icon">
          <BarChart2Icon class="w-4 h-4" />
        </div>
        <div class="min-w-0">
          <h2 id="mi-title" class="mi-title">My Attendance Insights</h2>
          <p class="mi-sub">Based on your own records only</p>
        </div>
      </div>

      <div class="mi-month" role="group" aria-label="Choose month">
        <button class="mi-month-btn" @click="shiftMonth(-1)" aria-label="Previous month">
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
        <span class="mi-month-label" aria-live="polite">{{ monthLabel }}</span>
        <button class="mi-month-btn" :disabled="isCurrentMonth" @click="shiftMonth(1)" aria-label="Next month">
          <ChevronRightIcon class="w-4 h-4" />
        </button>
      </div>
    </header>

    <div v-if="error" class="mi-error" role="alert">
      <span>Couldn't load your attendance for {{ monthLabel }}.</span>
      <button class="mi-error-btn" @click="load">Try again</button>
    </div>

    <!-- ── Headline numbers ───────────────────────────────────────── -->
    <div class="mi-kpis">
      <div class="mi-kpi">
        <div class="mi-ring" :style="{ '--ring': rateColor }" aria-hidden="true">
          <svg viewBox="0 0 44 44">
            <circle cx="22" cy="22" r="18" class="mi-ring-track" />
            <circle
              cx="22" cy="22" r="18" class="mi-ring-arc"
              :stroke-dasharray="RING"
              :stroke-dashoffset="RING - RING * (stats.rate ?? 0) / 100"
            />
          </svg>
        </div>
        <div class="min-w-0">
          <p class="mi-kpi-label">Attendance rate</p>
          <p class="mi-kpi-value">{{ stats.rate === null ? '—' : stats.rate + '%' }}</p>
          <p class="mi-kpi-sub">
            {{ stats.workingDays ? `${stats.present} of ${stats.workingDays} working days` : 'No working days yet' }}
          </p>
        </div>
      </div>

      <div class="mi-kpi">
        <div class="mi-kpi-icon mi-kpi-icon--green"><UserCheckIcon class="w-4 h-4" /></div>
        <div class="min-w-0">
          <p class="mi-kpi-label">Days present</p>
          <p class="mi-kpi-value">{{ stats.present }}</p>
          <p class="mi-kpi-sub">{{ stats.onTime }} on time</p>
        </div>
      </div>

      <div class="mi-kpi">
        <div class="mi-kpi-icon mi-kpi-icon--amber"><ClockIcon class="w-4 h-4" /></div>
        <div class="min-w-0">
          <p class="mi-kpi-label">Times late</p>
          <p class="mi-kpi-value">{{ stats.late }}</p>
          <p class="mi-kpi-sub">{{ stats.tardiness ? fmtMinutes(stats.tardiness) + ' total' : 'No tardiness' }}</p>
        </div>
      </div>

      <div class="mi-kpi">
        <div class="mi-kpi-icon mi-kpi-icon--navy"><HourglassIcon class="w-4 h-4" /></div>
        <div class="min-w-0">
          <p class="mi-kpi-label">Hours rendered</p>
          <p class="mi-kpi-value">{{ fmtHours(stats.hours) }}</p>
          <p class="mi-kpi-sub">
            {{ stats.present ? `Avg ${fmtHours(stats.hours / stats.present)} per day` : 'No hours yet' }}
          </p>
        </div>
      </div>
    </div>

    <div class="mi-grid">

      <!-- ── Calendar ─────────────────────────────────────────────── -->
      <div class="mi-card">
        <div class="mi-card-head">
          <CalendarDaysIcon class="mi-card-icon" />
          <div>
            <h3 class="mi-card-title">Monthly calendar</h3>
            <p class="mi-card-sub">Tap a day to see its time record</p>
          </div>
        </div>

        <div class="mi-cal" role="grid" :aria-label="`Attendance calendar for ${monthLabel}`">
          <div class="mi-cal-row mi-cal-row--head" role="row">
            <span v-for="d in WEEKDAYS" :key="d" class="mi-cal-dow" role="columnheader">{{ d }}</span>
          </div>
          <div class="mi-cal-days" role="row">
            <span v-for="n in leadingBlanks" :key="`b${n}`" class="mi-cal-blank" aria-hidden="true"></span>
            <button
              v-for="d in days"
              :key="d.date"
              type="button"
              role="gridcell"
              class="mi-day"
              :class="[`mi-day--${d.status}`, { 'mi-day--selected': selectedDate === d.date, 'mi-day--now': d.date === todayStr }]"
              :disabled="d.status === 'upcoming'"
              :aria-label="dayAriaLabel(d)"
              :aria-pressed="selectedDate === d.date"
              @click="selectedDate = d.date"
            >{{ d.day }}</button>
          </div>
        </div>

        <ul class="mi-legend">
          <li v-for="item in legend" :key="item.key" class="mi-legend-item">
            <i class="mi-swatch" :class="`mi-day--${item.key}`"></i>
            <span>{{ item.label }}</span>
            <strong>{{ item.count }}</strong>
          </li>
        </ul>

        <!-- Selected day detail -->
        <div v-if="selectedDay" class="mi-detail">
          <div class="mi-detail-head">
            <span class="mi-detail-date">{{ longDate(selectedDay.date) }}</span>
            <StatusBadge
              v-if="BADGE_STATUSES.includes(selectedDay.status)"
              :status="selectedDay.status"
            />
            <span v-else class="mi-detail-pending">No punch yet today</span>
          </div>
          <dl class="mi-detail-grid">
            <div><dt>Time in</dt><dd>{{ fmtTime(selectedDay.rec?.morning_in) }}</dd></div>
            <div><dt>Lunch out</dt><dd>{{ fmtTime(selectedDay.rec?.morning_out) }}</dd></div>
            <div><dt>Lunch in</dt><dd>{{ fmtTime(selectedDay.rec?.afternoon_in) }}</dd></div>
            <div><dt>Time out</dt><dd>{{ fmtTime(selectedDay.rec?.afternoon_out) }}</dd></div>
            <div><dt>Hours</dt><dd>{{ selectedDay.rec?.hours_rendered ? fmtHours(selectedDay.rec.hours_rendered) : '—' }}</dd></div>
            <div><dt>Late</dt><dd>{{ selectedDay.rec?.tardiness_minutes ? fmtMinutes(selectedDay.rec.tardiness_minutes) : '—' }}</dd></div>
          </dl>
        </div>

        <p class="mi-footnote">A past weekday with no punches counts as absent.</p>
      </div>

      <!-- ── Arrival times chart ──────────────────────────────────── -->
      <div class="mi-card">
        <div class="mi-card-head">
          <SunriseIcon class="mi-card-icon" />
          <div>
            <h3 class="mi-card-title">Arrival times</h3>
            <p class="mi-card-sub">
              Your morning time-in per day{{ shiftStart !== null ? ' · dashed line is your shift start' : '' }}
            </p>
          </div>
        </div>

        <div class="mi-chart">
          <canvas v-show="arrivals.length" ref="chartCanvas" role="img" :aria-label="`Arrival times for ${monthLabel}`"></canvas>
          <div v-if="!arrivals.length && !loading" class="mi-chart-empty">
            <SunriseIcon class="w-5 h-5" />
            <p>No time-in records for {{ monthLabel }} yet.</p>
          </div>
        </div>

        <div v-if="arrivals.length" class="mi-chart-stats">
          <div>
            <span class="mi-cs-label">Avg. time in</span>
            <span class="mi-cs-value">{{ fmtClock(stats.avgArrival) }}</span>
          </div>
          <div>
            <span class="mi-cs-label">Earliest</span>
            <span class="mi-cs-value">{{ fmtClock(stats.earliest) }}</span>
          </div>
          <div>
            <span class="mi-cs-label">Latest</span>
            <span class="mi-cs-value">{{ fmtClock(stats.latest) }}</span>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import { useAuthStore }  from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import StatusBadge from '@/components/StatusBadge.vue';
import {
  BarChart2    as BarChart2Icon,
  ChevronLeft  as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon,
  CalendarDays as CalendarDaysIcon,
  Sunrise      as SunriseIcon,
  UserCheck    as UserCheckIcon,
  Clock        as ClockIcon,
  Hourglass    as HourglassIcon,
} from 'lucide-vue-next';
import {
  Chart, LineController, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler,
} from 'chart.js';

Chart.register(LineController, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler);

defineProps({
  heroGradient: { type: String, default: '#1B2A5E' },
});

const auth  = useAuthStore();
const theme = useThemeStore();

const WEEKDAYS       = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const BADGE_STATUSES = ['present', 'late', 'absent', 'on_leave', 'holiday', 'weekend'];
const RING           = 2 * Math.PI * 18;
const MONTH_NAMES    = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                        'August', 'September', 'October', 'November', 'December'];
const SHORT_MONTHS   = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// ── Local-date helpers (never through toISOString(), which shifts to UTC) ──
const pad = (n) => String(n).padStart(2, '0');
function toLocalDateStr(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const now      = new Date();
const todayStr = toLocalDateStr(now);
const view     = reactive({ year: now.getFullYear(), month: now.getMonth() + 1 });

const isCurrentMonth = computed(() =>
  view.year === now.getFullYear() && view.month === now.getMonth() + 1
);
const monthLabel = computed(() => `${MONTH_NAMES[view.month - 1]} ${view.year}`);

function shiftMonth(step) {
  const d = new Date(view.year, view.month - 1 + step, 1);
  if (step > 0 && d > now) return;
  view.year  = d.getFullYear();
  view.month = d.getMonth() + 1;
}

// ── Data ─────────────────────────────────────────────────────────
const records = ref([]);
const loading = ref(false);
const error   = ref(false);
let requestSeq = 0;

async function load() {
  const id = auth.employee?.id;
  if (!id) return;
  const seq = ++requestSeq;
  loading.value = true;
  error.value   = false;
  try {
    const res = await axios.get(`/api/attendance/history/${id}`, {
      params: { month: view.month, year: view.year },
    });
    if (seq !== requestSeq) return; // a newer month was picked meanwhile
    records.value = res.data.records ?? [];
  } catch {
    if (seq !== requestSeq) return;
    records.value = [];
    error.value   = true;
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
  selectedDate.value = isCurrentMonth.value ? todayStr : null;
  await nextTick();
  buildChart();
}

// One entry per calendar day. Days without a daily_dtr row are filled in
// the same way the server computes them: weekends are 'weekend', a past
// weekday with no punches is 'absent', and today stays open until it ends.
const days = computed(() => {
  // work_date arrives as '2026-10-05T00:00:00.000000Z' — only the date part matters.
  const byDate = new Map(records.value.map(r => [String(r.work_date).slice(0, 10), r]));
  const total  = new Date(view.year, view.month, 0).getDate();

  return Array.from({ length: total }, (_, i) => {
    const day  = i + 1;
    const date = `${view.year}-${pad(view.month)}-${pad(day)}`;
    const dow  = new Date(view.year, view.month - 1, day).getDay();
    const rec  = byDate.get(date) ?? null;

    let status;
    if (date > todayStr)                                            status = 'upcoming';
    else if (date === todayStr && (!rec || rec.status === 'absent')) status = 'today';
    else if (rec?.status)                                           status = rec.status;
    else if (dow === 0 || dow === 6)                                status = 'weekend';
    else                                                            status = 'absent';

    return { day, date, dow, status, rec };
  });
});

const leadingBlanks = computed(() => (days.value[0].dow + 6) % 7); // Monday-first grid

const counts = computed(() => days.value.reduce((acc, d) => {
  acc[d.status] = (acc[d.status] ?? 0) + 1;
  return acc;
}, {}));

const arrivals = computed(() => days.value
  .filter(d => d.rec?.morning_in && ['present', 'late'].includes(d.status))
  .map(d => ({ ...d, minutes: toMinutes(d.rec.morning_in) }))
);

const stats = computed(() => {
  const c        = counts.value;
  const onTime   = c.present ?? 0;
  const late     = c.late ?? 0;
  const present  = onTime + late;
  const absent   = c.absent ?? 0;
  const working  = present + absent; // leave, holidays and weekends don't count against you
  const worked   = days.value.filter(d => ['present', 'late'].includes(d.status));
  const mins     = arrivals.value.map(a => a.minutes);

  return {
    onTime, late, present, absent,
    workingDays: working,
    rate:       working ? Math.round(present / working * 100) : null,
    tardiness:  worked.reduce((s, d) => s + (d.rec?.tardiness_minutes ?? 0), 0),
    hours:      worked.reduce((s, d) => s + Number(d.rec?.hours_rendered ?? 0), 0),
    avgArrival: mins.length ? Math.round(mins.reduce((a, b) => a + b, 0) / mins.length) : null,
    earliest:   mins.length ? Math.min(...mins) : null,
    latest:     mins.length ? Math.max(...mins) : null,
  };
});

const rateColor = computed(() => {
  const r = stats.value.rate;
  if (r === null) return '#94A3B8';
  if (r >= 75) return '#16A34A';
  if (r >= 50) return '#D97706';
  return '#DC2626';
});

const legend = computed(() => [
  { key: 'present',  label: 'On time',  count: counts.value.present  ?? 0 },
  { key: 'late',     label: 'Late',     count: counts.value.late     ?? 0 },
  { key: 'absent',   label: 'Absent',   count: counts.value.absent   ?? 0 },
  { key: 'on_leave', label: 'On leave', count: counts.value.on_leave ?? 0 },
  { key: 'holiday',  label: 'Holiday',  count: counts.value.holiday  ?? 0 },
]);

const selectedDate = ref(todayStr);
const selectedDay  = computed(() => days.value.find(d => d.date === selectedDate.value) ?? null);

// ── Formatting ───────────────────────────────────────────────────
function toMinutes(t) {
  const [h, m] = String(t).split(':').map(Number);
  return h * 60 + (m || 0);
}
function fmtClock(minutes) {
  if (minutes === null || minutes === undefined) return '—';
  const h = Math.floor(minutes / 60), m = minutes % 60;
  return `${h % 12 || 12}:${pad(m)} ${h < 12 ? 'AM' : 'PM'}`;
}
function fmtTime(t) {
  return t ? fmtClock(toMinutes(t)) : '—';
}
function fmtMinutes(m) {
  const h = Math.floor(m / 60), r = m % 60;
  if (!h) return `${r} min`;
  return r ? `${h} h ${r} min` : `${h} h`;
}
function fmtHours(h) {
  const v = Number(h) || 0;
  return `${Number.isInteger(v) ? v : v.toFixed(1)} h`;
}
const STATUS_LABELS = {
  present: 'Present', late: 'Late', absent: 'Absent', on_leave: 'On leave',
  holiday: 'Holiday', weekend: 'Weekend', today: 'Today, no punch yet', upcoming: 'Upcoming',
};
function longDate(date) {
  const [y, m, d] = date.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });
}
function dayAriaLabel(d) {
  const parts = [longDate(d.date), STATUS_LABELS[d.status] ?? d.status];
  if (d.rec?.morning_in) parts.push(`time in ${fmtTime(d.rec.morning_in)}`);
  return parts.join(', ');
}

// ── Arrival chart ────────────────────────────────────────────────
const chartCanvas = ref(null);
let chart = null;

// Shift start from the profile ('08:00:00' or '06:00'), when one is assigned.
const shiftStart = computed(() => {
  const t = auth.employee?.shift?.morning_in;
  return t ? toMinutes(t) : null;
});

function buildChart() {
  chart?.destroy();
  chart = null;
  if (!chartCanvas.value || !arrivals.value.length) return;

  const dark   = theme.isDark;
  const grid   = dark ? 'rgba(255,255,255,0.06)' : 'rgba(226,232,240,0.7)';
  const tick   = dark ? '#94A3B8' : '#6B7A94';
  const points = arrivals.value;
  const values = points.map(p => p.minutes);
  if (shiftStart.value !== null) values.push(shiftStart.value);
  const yMin = Math.floor((Math.min(...values) - 20) / 30) * 30;
  const yMax = Math.ceil((Math.max(...values) + 20) / 30) * 30;
  const pointColor = points.map(p => (p.status === 'late' ? '#D97706' : '#16A34A'));

  const datasets = [{
    label: 'Time in',
    data: points.map(p => p.minutes),
    borderColor: dark ? 'rgba(148,163,184,0.45)' : 'rgba(46,74,140,0.35)',
    borderWidth: 1.5,
    tension: 0.3,
    pointRadius: 4.5,
    pointHoverRadius: 6.5,
    pointBackgroundColor: pointColor,
    pointBorderColor: dark ? '#1C2A44' : '#fff',
    pointBorderWidth: 1.5,
  }];
  if (shiftStart.value !== null) {
    datasets.push({
      label: 'Shift start',
      data: points.map(() => shiftStart.value),
      borderColor: dark ? 'rgba(201,162,39,0.75)' : '#C9A227',
      borderDash: [6, 4],
      borderWidth: 1.5,
      pointRadius: 0,
      pointHoverRadius: 0,
      fill: false,
    });
  }

  chart = new Chart(chartCanvas.value, {
    type: 'line',
    data: { labels: points.map(p => `${SHORT_MONTHS[view.month - 1]} ${p.day}`), datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 500 },
      interaction: { mode: 'nearest', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1A2444',
          padding: 10,
          cornerRadius: 6,
          filter: item => item.datasetIndex === 0,
          callbacks: {
            label: (ctx) => {
              const p = points[ctx.dataIndex];
              return ` Time in ${fmtClock(p.minutes)} (${p.status === 'late' ? 'Late' : 'On time'})`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: tick, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
        },
        y: {
          min: yMin,
          max: yMax,
          grid: { color: grid, drawTicks: false },
          border: { display: false },
          ticks: { color: tick, font: { size: 10 }, stepSize: 30, callback: v => fmtClock(v) },
        },
      },
    },
  });
}

watch(() => [view.year, view.month], load);
watch(() => theme.isDark, buildChart);
onMounted(load);
onBeforeUnmount(() => chart?.destroy());

// The dashboard's Refresh button and live punch updates reload this too.
defineExpose({ load });
</script>

<style scoped>
/* ── Theme tokens ─────────────────────────────────────────────── */
.mi {
  --mi-card:    #fff;
  --mi-border:  #E4E9F2;
  --mi-soft:    #F7F9FC;
  --mi-ink:     #0F172A;
  --mi-muted:   #6B7A94;
  --mi-faint:   #CBD5E1;

  --st-present-bg: #DCFCE7; --st-present-fg: #166534;
  --st-late-bg:    #FEF3C7; --st-late-fg:    #92400E;
  --st-absent-bg:  #FEE2E2; --st-absent-fg:  #991B1B;
  --st-leave-bg:   #E0E7FF; --st-leave-fg:   #3730A3;
  --st-holiday-bg: #EDE9FE; --st-holiday-fg: #5B21B6;
  --st-weekend-bg: #F1F5F9; --st-weekend-fg: #94A3B8;

  background: var(--mi-card);
  border: 1px solid var(--mi-border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 32px -8px rgba(27, 42, 94, 0.13), 0 1px 4px rgba(27, 42, 94, 0.05);
  transition: opacity 0.2s ease;
}
:global([data-theme="dark"] .mi) {
  --mi-card:    #1C2A44;
  --mi-border:  rgba(255, 255, 255, 0.08);
  --mi-soft:    #17223A;
  --mi-ink:     #E4EAF5;
  --mi-muted:   #9AAAC9;
  --mi-faint:   #475569;

  --st-present-bg: rgba(22, 163, 74, 0.22);  --st-present-fg: #86EFAC;
  --st-late-bg:    rgba(217, 119, 6, 0.24);  --st-late-fg:    #FCD34D;
  --st-absent-bg:  rgba(220, 38, 38, 0.24);  --st-absent-fg:  #FCA5A5;
  --st-leave-bg:   rgba(99, 102, 241, 0.26); --st-leave-fg:   #C7D2FE;
  --st-holiday-bg: rgba(139, 92, 246, 0.26); --st-holiday-fg: #DDD6FE;
  --st-weekend-bg: rgba(148, 163, 184, 0.10); --st-weekend-fg: #64748B;
}
.mi--loading { opacity: 0.6; pointer-events: none; }

/* ── Header ───────────────────────────────────────────────────── */
.mi-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 14px 20px;
}
.mi-hero-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.mi-hero-icon {
  width: 32px; height: 32px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.14);
  color: rgba(255, 255, 255, 0.92);
}
.mi-title { font-size: 14px; font-weight: 700; color: #fff; line-height: 1.2; }
.mi-sub   { font-size: 11.5px; color: rgba(255, 255, 255, 0.6); margin-top: 2px; }

.mi-month {
  display: flex; align-items: center; gap: 4px;
  padding: 3px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.14);
}
.mi-month-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 7px;
  color: #fff;
  transition: background 0.15s ease;
}
.mi-month-btn:hover:not(:disabled) { background: rgba(255, 255, 255, 0.16); }
.mi-month-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.mi-month-label {
  min-width: 128px;
  text-align: center;
  font-size: 13px; font-weight: 600; color: #fff;
}

.mi-error {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin: 14px 20px 0;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 12.5px;
  background: var(--st-absent-bg);
  color: var(--st-absent-fg);
}
.mi-error-btn { font-weight: 700; text-decoration: underline; }

/* ── KPI tiles ────────────────────────────────────────────────── */
.mi-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  padding: 16px 20px 0;
}
.mi-kpi {
  display: flex; align-items: center; gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: var(--mi-soft);
  border: 1px solid var(--mi-border);
  min-width: 0;
}
.mi-kpi-icon {
  width: 40px; height: 40px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 10px;
  color: #fff;
}
.mi-kpi-icon--green { background: linear-gradient(135deg, #22C55E, #15803D); }
.mi-kpi-icon--amber { background: linear-gradient(135deg, #F59E0B, #B45309); }
.mi-kpi-icon--navy  { background: linear-gradient(135deg, #3D5CA3, #1B2A5E); }
.mi-kpi-label { font-size: 11px; font-weight: 600; color: var(--mi-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.mi-kpi-value { font-size: 22px; font-weight: 800; color: var(--mi-ink); line-height: 1.15; margin-top: 2px; font-variant-numeric: tabular-nums; }
.mi-kpi-sub   { font-size: 11.5px; color: var(--mi-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.mi-ring { width: 40px; height: 40px; flex-shrink: 0; }
.mi-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.mi-ring-track { fill: none; stroke: var(--mi-border); stroke-width: 5; }
.mi-ring-arc {
  fill: none;
  stroke: var(--ring);
  stroke-width: 5;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s ease, stroke 0.3s ease;
}

/* ── Two-card grid ────────────────────────────────────────────── */
.mi-grid {
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
  gap: 12px;
  padding: 12px 20px 20px;
}
.mi-card {
  border: 1px solid var(--mi-border);
  border-radius: 12px;
  padding: 16px;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.mi-card-head { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
.mi-card-icon { width: 18px; height: 18px; color: var(--mi-muted); flex-shrink: 0; margin-top: 1px; }
.mi-card-title { font-size: 13.5px; font-weight: 700; color: var(--mi-ink); }
.mi-card-sub   { font-size: 11.5px; color: var(--mi-muted); margin-top: 1px; }

/* ── Calendar ─────────────────────────────────────────────────── */
.mi-cal-row--head,
.mi-cal-days {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 5px;
}
.mi-cal-row--head { margin-bottom: 5px; }
.mi-cal-dow {
  text-align: center;
  font-size: 10.5px; font-weight: 600;
  color: var(--mi-muted);
  text-transform: uppercase; letter-spacing: 0.03em;
}
.mi-day {
  aspect-ratio: 1 / 1;
  min-height: 32px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 8px;
  font-size: 12.5px; font-weight: 600;
  font-variant-numeric: tabular-nums;
  border: 1.5px solid transparent;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.mi-day:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(15, 23, 42, 0.12); }
.mi-day:disabled { cursor: default; }

.mi-day--present  { background: var(--st-present-bg); color: var(--st-present-fg); }
.mi-day--late     { background: var(--st-late-bg);    color: var(--st-late-fg); }
.mi-day--absent   { background: var(--st-absent-bg);  color: var(--st-absent-fg); }
.mi-day--on_leave { background: var(--st-leave-bg);   color: var(--st-leave-fg); }
.mi-day--holiday  { background: var(--st-holiday-bg); color: var(--st-holiday-fg); }
.mi-day--weekend  { background: var(--st-weekend-bg); color: var(--st-weekend-fg); }
.mi-day--today    { background: transparent; color: var(--mi-ink); border-color: #3D5CA3; }
.mi-day--upcoming { background: transparent; color: var(--mi-faint); border: 1.5px dashed var(--mi-border); }

.mi-day--now      { box-shadow: 0 0 0 2px var(--mi-card), 0 0 0 3.5px #3D5CA3; }
.mi-day--selected { border-color: var(--mi-ink); }

/* ── Legend ───────────────────────────────────────────────────── */
.mi-legend {
  display: flex; flex-wrap: wrap;
  gap: 6px 14px;
  margin-top: 14px;
}
.mi-legend-item { display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--mi-muted); }
.mi-legend-item strong { color: var(--mi-ink); font-weight: 700; font-variant-numeric: tabular-nums; }
.mi-swatch { width: 10px; height: 10px; border-radius: 3px; display: inline-block; border: 1.5px solid currentColor; }

/* ── Selected day detail ──────────────────────────────────────── */
.mi-detail {
  margin-top: 14px;
  padding: 12px;
  border-radius: 10px;
  background: var(--mi-soft);
  border: 1px solid var(--mi-border);
}
.mi-detail-head {
  display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
  margin-bottom: 10px;
}
.mi-detail-date    { font-size: 12.5px; font-weight: 700; color: var(--mi-ink); }
.mi-detail-pending { font-size: 11.5px; font-weight: 600; color: var(--mi-muted); }
.mi-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 8px;
}
.mi-detail-grid dt { font-size: 10px; font-weight: 600; color: var(--mi-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.mi-detail-grid dd { font-size: 13px; font-weight: 700; color: var(--mi-ink); margin-top: 1px; font-variant-numeric: tabular-nums; }

.mi-footnote { font-size: 11px; color: var(--mi-muted); margin-top: 12px; }

/* ── Arrival chart ────────────────────────────────────────────── */
.mi-chart { position: relative; height: 260px; flex: 1; min-height: 220px; }
.mi-chart-empty {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
  font-size: 12.5px;
  color: var(--mi-muted);
  border: 1.5px dashed var(--mi-border);
  border-radius: 10px;
}
.mi-chart-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--mi-border);
}
.mi-chart-stats > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mi-cs-label { font-size: 10.5px; font-weight: 600; color: var(--mi-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.mi-cs-value { font-size: 15px; font-weight: 800; color: var(--mi-ink); font-variant-numeric: tabular-nums; }

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .mi-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .mi-grid { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
  .mi-hero  { padding: 12px 14px; }
  .mi-month { width: 100%; justify-content: space-between; }
  .mi-month-label { min-width: 0; flex: 1; }
  .mi-error { margin: 12px 14px 0; }
  .mi-kpis  { padding: 12px 14px 0; gap: 8px; }
  .mi-kpi   { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px; }
  .mi-kpi-value { font-size: 20px; }
  .mi-kpi-sub   { white-space: normal; }
  .mi-grid  { padding: 8px 14px 14px; gap: 8px; }
  .mi-card  { padding: 12px; }
  .mi-cal-row--head, .mi-cal-days { gap: 4px; }
  .mi-day   { font-size: 12px; border-radius: 7px; min-height: 0; }
  .mi-chart { height: 220px; min-height: 0; }
  .mi-cs-value { font-size: 13.5px; }
}
@media (max-width: 360px) {
  .mi-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
