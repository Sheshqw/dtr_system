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
        <button class="mi-month-btn" :disabled="!canGoBack" @click="shiftMonth(-1)" aria-label="Previous month">
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
          <p class="mi-kpi-value">
            {{ stats.rate === null ? '—' : stats.rate + '%' }}
            <span
              v-if="rateDelta"
              class="mi-delta"
              :class="rateDelta.value > 0 ? 'mi-delta--up' : rateDelta.value < 0 ? 'mi-delta--down' : 'mi-delta--flat'"
              :title="`Compared with ${rateDelta.label}`"
            >
              {{ rateDelta.value > 0 ? '▲' : rateDelta.value < 0 ? '▼' : '•' }}
              {{ Math.abs(rateDelta.value) }} pts
            </span>
          </p>
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
              :disabled="d.status === 'upcoming' || d.status === 'none'"
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

      <!-- ── Daily chart: arrival times or hours worked ───────────── -->
      <div class="mi-card">
        <div class="mi-card-head mi-card-head--split">
          <div class="mi-card-head">
            <component :is="chartMode === 'arrival' ? SunriseIcon : TimerIcon" class="mi-card-icon" />
            <div>
              <h3 class="mi-card-title">{{ chartMode === 'arrival' ? 'Arrival times' : 'Hours worked' }}</h3>
              <p class="mi-card-sub">{{ chartSubtitle }}</p>
            </div>
          </div>
          <div class="mi-seg" role="tablist" aria-label="Chart view">
            <button
              v-for="m in CHART_MODES" :key="m.key"
              type="button" role="tab"
              class="mi-seg-btn" :class="{ 'mi-seg-btn--on': chartMode === m.key }"
              :aria-selected="chartMode === m.key"
              @click="chartMode = m.key"
            >{{ m.label }}</button>
          </div>
        </div>

        <div class="mi-chart">
          <canvas v-show="workedDays.length" ref="chartCanvas" role="img" :aria-label="`${chartMode === 'arrival' ? 'Arrival times' : 'Hours worked'} for ${monthLabel}`"></canvas>
          <div v-if="!workedDays.length && !loading" class="mi-chart-empty">
            <SunriseIcon class="w-5 h-5" />
            <p>No worked days in {{ monthLabel }} yet.</p>
          </div>
        </div>

        <div v-if="workedDays.length && chartMode === 'arrival'" class="mi-chart-stats">
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
        <div v-else-if="workedDays.length" class="mi-chart-stats">
          <div>
            <span class="mi-cs-label">Avg. per day</span>
            <span class="mi-cs-value">{{ fmtHours(stats.hours / stats.present) }}</span>
          </div>
          <div>
            <span class="mi-cs-label">Full days</span>
            <span class="mi-cs-value">{{ requiredHours ? `${stats.fullDays} of ${stats.present}` : '—' }}</span>
          </div>
          <div>
            <span class="mi-cs-label">Undertime</span>
            <span class="mi-cs-value">{{ stats.undertime ? fmtMinutes(stats.undertime) : 'None' }}</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Trends & patterns (from the summary endpoint) ──────────── -->
    <div class="mi-section-label">
      <TrendingUpIcon class="w-3.5 h-3.5" />
      <span>Trends &amp; patterns · {{ trendRangeLabel }}</span>
    </div>

    <div class="mi-grid mi-grid--trends">

      <div class="mi-card">
        <div class="mi-card-head">
          <BarChart2Icon class="mi-card-icon" />
          <div>
            <h3 class="mi-card-title">Monthly trend</h3>
            <p class="mi-card-sub">Attendance rate per month (bars) and late days (line)</p>
          </div>
        </div>
        <div class="mi-chart mi-chart--short">
          <canvas v-show="trendMonths.length" ref="trendCanvas" role="img" aria-label="Monthly attendance trend"></canvas>
          <div v-if="!trendMonths.length" class="mi-chart-empty">
            <BarChart2Icon class="w-5 h-5" />
            <p>{{ summary ? 'No months to show yet.' : 'Loading your trend…' }}</p>
          </div>
        </div>
      </div>

      <div class="mi-card">
        <div class="mi-card-head">
          <FlameIcon class="mi-card-icon" />
          <div>
            <h3 class="mi-card-title">Punctuality</h3>
            <p class="mi-card-sub">On-time streaks and late arrivals by weekday</p>
          </div>
        </div>

        <div class="mi-streaks">
          <div class="mi-streak">
            <span class="mi-streak-value">{{ summary?.streak?.current ?? '—' }}</span>
            <span class="mi-streak-label">Current on-time streak <small>(working days)</small></span>
          </div>
          <div class="mi-streak">
            <span class="mi-streak-value">{{ summary?.streak?.best ?? '—' }}</span>
            <span class="mi-streak-label">Best streak · {{ trendRangeLabel }}</span>
          </div>
        </div>

        <ul class="mi-weekdays">
          <li v-for="w in weekdayRows" :key="w.dow" class="mi-wd">
            <span class="mi-wd-label">{{ w.label }}</span>
            <span class="mi-wd-track" :title="`${w.late} late of ${w.worked} worked`">
              <span class="mi-wd-fill" :style="{ width: w.worked ? (w.late / maxWeekdayLate) * 100 + '%' : '0%' }"></span>
            </span>
            <span class="mi-wd-meta">
              <strong>{{ w.late }}</strong> late of {{ w.worked }}
              <span class="mi-wd-avg">· avg in {{ fmtClock(w.avg_arrival) }}</span>
            </span>
          </li>
        </ul>

        <p class="mi-insight">{{ punctualityInsight }}</p>
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
  Timer        as TimerIcon,
  TrendingUp   as TrendingUpIcon,
  Flame        as FlameIcon,
  UserCheck    as UserCheckIcon,
  Clock        as ClockIcon,
  Hourglass    as HourglassIcon,
} from 'lucide-vue-next';
import {
  Chart, LineController, BarController, CategoryScale, LinearScale,
  PointElement, LineElement, BarElement, Tooltip, Filler,
} from 'chart.js';

Chart.register(LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Filler);

const props = defineProps({
  heroGradient: { type: String, default: '#1B2A5E' },
  // /api/me/attendance-summary — shift, tracking start, monthly trend,
  // streaks and weekday pattern. Loaded by the parent; null while loading.
  summary:      { type: Object, default: null },
});

const auth  = useAuthStore();
const theme = useThemeStore();

const WEEKDAYS       = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const BADGE_STATUSES = ['present', 'late', 'absent', 'on_leave', 'holiday', 'weekend'];
const RING           = 2 * Math.PI * 18;
const MONTH_NAMES    = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                        'August', 'September', 'October', 'November', 'December'];
const SHORT_MONTHS   = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WEEKDAY_NAMES  = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday' };
const CHART_MODES    = [{ key: 'arrival', label: 'Arrival' }, { key: 'hours', label: 'Hours' }];

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

// First day attendance counts for this employee (hire date, or first record).
const since = computed(() => props.summary?.since ?? null);
const canGoBack = computed(() =>
  !since.value || `${view.year}-${pad(view.month)}` > since.value.slice(0, 7)
);

function shiftMonth(step) {
  if (step < 0 && !canGoBack.value) return;
  const d = new Date(view.year, view.month - 1 + step, 1);
  if (step > 0 && d > now) return;
  view.year  = d.getFullYear();
  view.month = d.getMonth() + 1;
}

// ── Month data ───────────────────────────────────────────────────
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

// One entry per calendar day, classified exactly like the server's
// summary endpoint: a daily_dtr status wins; otherwise weekends are
// 'weekend' and past weekdays 'absent'; today stays open until it has a
// punch; days before the employee's tracking start don't count ('none').
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
    else if (since.value && date < since.value)                     status = 'none';
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

const workedDays = computed(() => days.value.filter(d => d.rec && ['present', 'late'].includes(d.status)));
const arrivals   = computed(() => workedDays.value
  .filter(d => d.rec.morning_in)
  .map(d => ({ ...d, minutes: toMinutes(d.rec.morning_in) }))
);

// Shift from the summary endpoint (the login payload doesn't carry it).
const shift         = computed(() => props.summary?.shift ?? null);
const shiftStart    = computed(() => (shift.value?.morning_in ? toMinutes(shift.value.morning_in) : null));
const requiredHours = computed(() => (shift.value?.required_hours ? Number(shift.value.required_hours) : null));

const stats = computed(() => {
  const c       = counts.value;
  const onTime  = c.present ?? 0;
  const late    = c.late ?? 0;
  const present = onTime + late;
  const absent  = c.absent ?? 0;
  const working = present + absent; // leave, holidays and weekends don't count against you
  const worked  = workedDays.value;
  const mins    = arrivals.value.map(a => a.minutes);

  return {
    onTime, late, present, absent,
    workingDays: working,
    rate:       working ? Math.round(present / working * 100) : null,
    tardiness:  worked.reduce((s, d) => s + (d.rec.tardiness_minutes ?? 0), 0),
    undertime:  worked.reduce((s, d) => s + (d.rec.undertime_minutes ?? 0), 0),
    hours:      worked.reduce((s, d) => s + Number(d.rec.hours_rendered ?? 0), 0),
    fullDays:   requiredHours.value
      ? worked.filter(d => Number(d.rec.hours_rendered ?? 0) >= requiredHours.value).length
      : 0,
    avgArrival: mins.length ? Math.round(mins.reduce((a, b) => a + b, 0) / mins.length) : null,
    earliest:   mins.length ? Math.min(...mins) : null,
    latest:     mins.length ? Math.max(...mins) : null,
  };
});

const rateColor = computed(() => tierColor(stats.value.rate));
function tierColor(r) {
  if (r === null || r === undefined) return '#94A3B8';
  if (r >= 75) return '#16A34A';
  if (r >= 50) return '#D97706';
  return '#DC2626';
}

// Rate vs the previous month — a rate compares fairly even while the
// current month is only partly through, unlike raw day counts.
const rateDelta = computed(() => {
  const months = props.summary?.months ?? [];
  const prev   = new Date(view.year, view.month - 2, 1);
  const p      = months.find(m => m.year === prev.getFullYear() && m.month === prev.getMonth() + 1);
  if (!p || p.rate === null || stats.value.rate === null) return null;
  return { value: stats.value.rate - p.rate, label: `${MONTH_NAMES[p.month - 1]} (${p.rate}%)` };
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

// ── Trends & patterns ────────────────────────────────────────────
const trendMonths = computed(() => props.summary?.months ?? []);
const trendRangeLabel = computed(() => {
  const n = trendMonths.value.length;
  return n > 1 ? `last ${n} months` : 'this month';
});
const weekdayRows    = computed(() => props.summary?.weekdays ?? []);
const maxWeekdayLate = computed(() => Math.max(1, ...weekdayRows.value.map(w => w.late)));
const punctualityInsight = computed(() => {
  const rows = weekdayRows.value;
  if (!rows.length) return '';
  const totalLate = rows.reduce((s, w) => s + w.late, 0);
  const worked    = rows.reduce((s, w) => s + w.worked, 0);
  if (!worked) return 'No worked days recorded yet.';
  if (!totalLate) return `No late arrivals in the ${trendRangeLabel.value}. Keep it up!`;
  const worst = rows.reduce((a, b) => (b.late > a.late ? b : a));
  return `You were late most often on ${WEEKDAY_NAMES[worst.dow]}s (${worst.late} of ${worst.worked} days).`;
});

const chartSubtitle = computed(() => {
  if (chartMode.value === 'arrival') {
    return `Your morning time-in per day${shiftStart.value !== null ? ' · dashed line is your shift start' : ''}`;
  }
  return `Hours rendered per day${requiredHours.value ? ` · dashed line is your required ${requiredHours.value} h` : ''}`;
});

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
  none: 'Before your start date',
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

// ── Charts ───────────────────────────────────────────────────────
const chartCanvas = ref(null);
const trendCanvas = ref(null);
const chartMode   = ref('arrival');
let chart      = null;
let trendChart = null;

function palette() {
  const dark = theme.isDark;
  return {
    dark,
    grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(226,232,240,0.7)',
    tick: dark ? '#94A3B8' : '#6B7A94',
    gold: dark ? 'rgba(201,162,39,0.75)' : '#C9A227',
  };
}
const statusColor = (s) => (s === 'late' ? '#D97706' : '#16A34A');
const tooltipBase = { backgroundColor: '#1A2444', padding: 10, cornerRadius: 6 };

function buildChart() {
  chart?.destroy();
  chart = null;
  if (!chartCanvas.value || !workedDays.value.length) return;
  chart = chartMode.value === 'arrival' ? arrivalChart() : hoursChart();
}

function referenceLine(label, length, value, color) {
  return {
    type: 'line', label,
    data: Array(length).fill(value),
    borderColor: color, borderDash: [6, 4], borderWidth: 1.5,
    pointRadius: 0, pointHoverRadius: 0, fill: false,
  };
}

function arrivalChart() {
  const { dark, grid, tick, gold } = palette();
  const points = arrivals.value;
  if (!points.length) return null;
  const values = points.map(p => p.minutes);
  if (shiftStart.value !== null) values.push(shiftStart.value);
  const yMin = Math.floor((Math.min(...values) - 20) / 30) * 30;
  const yMax = Math.ceil((Math.max(...values) + 20) / 30) * 30;

  const datasets = [{
    label: 'Time in',
    data: points.map(p => p.minutes),
    borderColor: dark ? 'rgba(148,163,184,0.45)' : 'rgba(46,74,140,0.35)',
    borderWidth: 1.5,
    tension: 0.3,
    pointRadius: 4.5,
    pointHoverRadius: 6.5,
    pointBackgroundColor: points.map(p => statusColor(p.status)),
    pointBorderColor: dark ? '#1C2A44' : '#fff',
    pointBorderWidth: 1.5,
  }];
  if (shiftStart.value !== null) datasets.push(referenceLine('Shift start', points.length, shiftStart.value, gold));

  return new Chart(chartCanvas.value, {
    type: 'line',
    data: { labels: points.map(p => `${SHORT_MONTHS[view.month - 1]} ${p.day}`), datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 500 },
      interaction: { mode: 'nearest', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          ...tooltipBase,
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
        x: { grid: { display: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
        y: { min: yMin, max: yMax, grid: { color: grid, drawTicks: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 }, stepSize: 30, callback: v => fmtClock(v) } },
      },
    },
  });
}

function hoursChart() {
  const { grid, tick, gold } = palette();
  const rows   = workedDays.value;
  const hours  = rows.map(d => Number(d.rec.hours_rendered ?? 0));
  const yMax   = Math.ceil(Math.max(requiredHours.value ?? 0, ...hours) + 1);

  const datasets = [{
    type: 'bar',
    label: 'Hours',
    data: hours,
    backgroundColor: rows.map(d => statusColor(d.status) + 'CC'),
    borderRadius: 4,
    maxBarThickness: 18,
  }];
  if (requiredHours.value) datasets.push(referenceLine('Required', rows.length, requiredHours.value, gold));

  return new Chart(chartCanvas.value, {
    type: 'bar',
    data: { labels: rows.map(d => `${SHORT_MONTHS[view.month - 1]} ${d.day}`), datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 500 },
      plugins: {
        legend: { display: false },
        tooltip: {
          ...tooltipBase,
          filter: item => item.datasetIndex === 0,
          callbacks: { label: (ctx) => ` ${fmtHours(ctx.parsed.y)} (${rows[ctx.dataIndex].status === 'late' ? 'Late' : 'On time'})` },
        },
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
        y: { min: 0, max: yMax, grid: { color: grid, drawTicks: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 }, stepSize: 2, callback: v => `${v} h` } },
      },
    },
  });
}

function buildTrendChart() {
  trendChart?.destroy();
  trendChart = null;
  const months = trendMonths.value;
  if (!trendCanvas.value || !months.length) return;
  const { grid, tick } = palette();

  trendChart = new Chart(trendCanvas.value, {
    type: 'bar',
    data: {
      labels: months.map(m => SHORT_MONTHS[m.month - 1]),
      datasets: [
        {
          type: 'bar',
          label: 'Attendance rate',
          data: months.map(m => m.rate),
          backgroundColor: months.map(m => tierColor(m.rate) + 'CC'),
          borderRadius: 5,
          maxBarThickness: 34,
          yAxisID: 'y',
          order: 2,
        },
        {
          type: 'line',
          label: 'Late days',
          data: months.map(m => m.late),
          borderColor: '#D97706',
          backgroundColor: '#D97706',
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 3.5,
          yAxisID: 'y1',
          order: 1,
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 500 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          ...tooltipBase,
          callbacks: {
            title: items => months[items[0].dataIndex].label,
            label: (ctx) => {
              const m = months[ctx.dataIndex];
              return ctx.datasetIndex === 0
                ? ` Attendance ${m.rate === null ? '—' : m.rate + '%'} (${m.present} of ${m.working_days} days)`
                : ` Late ${m.late} day${m.late === 1 ? '' : 's'}`;
            },
          },
        },
      },
      scales: {
        x:  { grid: { display: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 } } },
        y:  { min: 0, max: 100, grid: { color: grid, drawTicks: false }, border: { display: false }, ticks: { color: tick, font: { size: 10 }, stepSize: 25, callback: v => `${v}%` } },
        y1: { position: 'right', min: 0, suggestedMax: 5, grid: { display: false }, border: { display: false }, ticks: { color: '#D97706', font: { size: 10 }, precision: 0 } },
      },
    },
  });
}

watch(() => [view.year, view.month], load);
watch(chartMode, buildChart);
watch(() => props.summary, async () => {
  await nextTick();
  buildTrendChart();
  buildChart(); // the shift reference lines come from the summary
});
watch(() => theme.isDark, () => { buildChart(); buildTrendChart(); });
onMounted(load);
onBeforeUnmount(() => { chart?.destroy(); trendChart?.destroy(); });

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

/* ── Days before the employee's start date ────────────────────── */
.mi-day--none { background: transparent; color: var(--mi-faint); opacity: 0.55; }

/* ── Month-over-month delta chip ──────────────────────────────── */
.mi-delta {
  display: inline-flex; align-items: center; gap: 2px;
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 10.5px; font-weight: 700;
  vertical-align: middle;
}
.mi-delta--up   { background: var(--st-present-bg); color: var(--st-present-fg); }
.mi-delta--down { background: var(--st-absent-bg);  color: var(--st-absent-fg); }
.mi-delta--flat { background: var(--st-weekend-bg); color: var(--mi-muted); }

/* ── Chart view toggle ────────────────────────────────────────── */
.mi-card-head--split { justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.mi-card-head--split > .mi-card-head { margin-bottom: 0; }
.mi-seg {
  display: inline-flex;
  padding: 3px;
  border-radius: 9px;
  background: var(--mi-soft);
  border: 1px solid var(--mi-border);
}
.mi-seg-btn {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 11.5px; font-weight: 600;
  color: var(--mi-muted);
  transition: background 0.15s ease, color 0.15s ease;
}
.mi-seg-btn--on {
  background: var(--mi-card);
  color: var(--mi-ink);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
}

/* ── Trends & patterns ────────────────────────────────────────── */
.mi-section-label {
  display: flex; align-items: center; gap: 6px;
  padding: 4px 20px 0;
  font-size: 11px; font-weight: 700;
  color: var(--mi-muted);
  text-transform: uppercase; letter-spacing: 0.06em;
}
.mi-grid--trends { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); }
.mi-chart--short { height: 230px; min-height: 0; }

.mi-streaks {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.mi-streak {
  display: flex; flex-direction: column; gap: 2px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--mi-soft);
  border: 1px solid var(--mi-border);
}
.mi-streak-value { font-size: 22px; font-weight: 800; color: var(--mi-ink); line-height: 1.1; font-variant-numeric: tabular-nums; }
.mi-streak-label { font-size: 11px; color: var(--mi-muted); }
.mi-streak-label small { font-size: 10px; }

.mi-weekdays { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; }
.mi-wd {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}
.mi-wd-label { font-size: 11.5px; font-weight: 700; color: var(--mi-ink); }
.mi-wd-track {
  height: 8px;
  border-radius: 999px;
  background: var(--mi-soft);
  border: 1px solid var(--mi-border);
  overflow: hidden;
}
.mi-wd-fill {
  display: block; height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #F59E0B, #D97706);
  transition: width 0.5s ease;
}
.mi-wd-meta { font-size: 11px; color: var(--mi-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
.mi-wd-meta strong { color: var(--mi-ink); }

.mi-insight {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 12px;
  color: var(--mi-ink);
  background: var(--mi-soft);
  border-left: 3px solid #C9A227;
}

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .mi-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .mi-grid, .mi-grid--trends { grid-template-columns: minmax(0, 1fr); }
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
  .mi-section-label { padding: 2px 14px 0; }
  .mi-chart--short { height: 200px; }
  .mi-wd { grid-template-columns: 30px minmax(0, 1fr); }
  .mi-wd-meta { grid-column: 2; margin-top: -4px; }
}
@media (max-width: 360px) {
  .mi-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
