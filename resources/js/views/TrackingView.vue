<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Tracking" subtitle="Employee tardiness & absence analytics" :icon="BarChart2Icon" />

    <div class="flex-1 overflow-auto bg-[#F4F6FB] p-6 space-y-4">

      <!-- Tabs -->
      <div class="flex gap-1 bg-white border border-[#E2E8F0] rounded-xl p-1 w-fit">
        <button
          @click="activeTab = 'tardiness'"
          class="tab-btn" :class="{ 'tab-btn--active': activeTab === 'tardiness' }"
        >
          <ClockIcon class="w-4 h-4" /> Tardiness
        </button>
        <button
          @click="activeTab = 'bradford'"
          class="tab-btn" :class="{ 'tab-btn--active': activeTab === 'bradford' }"
        >
          <UserXIcon class="w-4 h-4" /> Habitual Absence
        </button>
      </div>

      <!-- ═══════════════════════ TARDINESS TAB ═══════════════════════ -->
      <template v-if="activeTab === 'tardiness'">

      <!-- Filters -->
      <div class="bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="filter-label">Month</label>
          <select v-model="tardyFilters.month" class="filter-input">
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
        <div>
          <label class="filter-label">Year</label>
          <select v-model="tardyFilters.year" class="filter-input">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div v-if="auth.isAdmin || auth.isHrOfficer">
          <label class="filter-label">Department</label>
          <select v-model="tardyFilters.department_id" class="filter-input w-44">
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="filter-label">Search</label>
          <input v-model="tardyFilters.search" type="text" placeholder="Name or ID…" class="filter-input w-44" />
        </div>
        <button @click="loadTardiness" :disabled="tardyLoading" class="refresh-btn ml-auto">
          <Loader2Icon v-if="tardyLoading" class="w-4 h-4 animate-spin" />
          <RefreshCwIcon v-else class="w-4 h-4" />
          Refresh
        </button>
        <button
          v-if="tardyRecords.length"
          @click="exportTardyCsv"
          class="inline-flex items-center gap-2 px-4 py-1.5 bg-white border border-[#E2E8F0] text-[#374151] text-sm font-medium rounded-lg hover:bg-[#F8FAFC] transition-colors export-btn"
        >
          <DownloadIcon class="w-4 h-4" />
          Export CSV
        </button>
        <button
          v-if="tardyRecords.length"
          @click="printReport"
          class="inline-flex items-center gap-2 px-4 py-1.5 bg-white border border-[#E2E8F0] text-[#374151] text-sm font-medium rounded-lg hover:bg-[#F8FAFC] transition-colors no-print"
        >
          <PrinterIcon class="w-4 h-4" />
          Print
        </button>
      </div>

      <!-- Summary cards -->
      <div v-if="tardyRecords.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-for="s in tardySummary" :key="s.label"
             class="tk-stat-card" :style="{ background: s.wash }">
          <div class="tk-stat-accent" :style="{ background: s.accent }"></div>
          <div class="flex items-center gap-3">
            <div class="tk-stat-icon" :style="{ background: s.accent + '1A', color: s.accent }">
              <component :is="s.icon" class="w-5 h-5" />
            </div>
            <div>
              <p class="tk-stat-value">{{ s.value }}</p>
              <p class="tk-stat-label">{{ s.label }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Skeleton -->
      <div v-if="tardyLoading" class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-4 py-4 border-b border-[#E2E8F0] last:border-0">
          <div class="w-8 h-8 rounded-full bg-slate-200 animate-pulse"></div>
          <div class="flex-1 space-y-1.5">
            <div class="h-3 w-36 rounded bg-slate-200 animate-pulse"></div>
            <div class="h-2.5 w-24 rounded bg-slate-200 animate-pulse"></div>
          </div>
          <div class="h-6 w-16 rounded-md bg-slate-200 animate-pulse"></div>
          <div class="h-6 w-20 rounded-md bg-slate-200 animate-pulse"></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="!tardyRecords.length" class="bg-white border border-[#E2E8F0] rounded-xl flex flex-col items-center justify-center h-48 text-[#6B7A94]">
        <CheckCircleIcon class="w-10 h-10 mb-2 opacity-30 text-emerald-500" />
        <p class="text-sm font-medium text-[#64748B]">No tardiness records</p>
        <p class="text-xs mt-1">Everyone was on time this period!</p>
      </div>

      <!-- Leaderboard chart + table -->
      <template v-else>
      <div class="tk-analytics-row">
        <div class="chart-panel tk-leaderboard-panel">
          <div class="cp-header">
            <div>
              <h2 class="cp-title">Top Tardiness Leaderboard</h2>
              <p class="cp-sub">Ranked by total minutes late this period</p>
            </div>
          </div>
          <div class="tardy-chart-body">
            <canvas ref="tardyChartCanvas" role="img" aria-label="Top tardiness leaderboard"></canvas>
          </div>
        </div>

        <div class="chart-panel tk-severity-panel">
          <div class="cp-header">
            <div>
              <h2 class="cp-title">Severity Mix</h2>
              <p class="cp-sub">Share of employees by tardiness severity</p>
            </div>
          </div>
          <div class="tardy-donut-body">
            <canvas ref="severityChartCanvas" role="img" aria-label="Tardiness severity distribution"></canvas>
          </div>
        </div>
      </div>

      <div class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
        <div class="tk-table-scroll">
        <table class="w-full text-sm tk-tardy-table">
          <thead class="border-b border-[#E2E8F0]">
            <tr>
              <th class="th-cell text-left w-10">#</th>
              <th class="th-cell text-left">Employee</th>
              <th class="th-cell text-center">Severity</th>
              <th class="th-cell text-center font-mono">Times Late</th>
              <th class="th-cell text-center font-mono">Total (min)</th>
              <th class="th-cell text-center font-mono">Avg (min)</th>
              <th class="th-cell text-center font-mono">Worst (min)</th>
              <th class="th-cell text-left">Recent Dates</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(rec, idx) in tardyRecords" :key="rec.employee_id"
              class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#FAFBFF] transition-colors"
            >
              <td class="td-cell text-[#6B7A94] font-mono text-xs" data-label="Rank">
                <span v-if="idx < 3" class="tk-medal" :class="`tk-medal--${idx}`">{{ idx + 1 }}</span>
                <template v-else>{{ idx + 1 }}</template>
              </td>
              <td class="td-cell tk-emp-cell">
                <div class="flex items-center gap-2">
                  <div
                    class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                    :style="avatarGradient(rec.times_late)"
                  >{{ initials(rec.name) }}</div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ rec.name }}</p>
                    <p class="text-xs text-[#6B7A94]">{{ rec.department }}</p>
                  </div>
                </div>
              </td>
              <td class="td-cell text-center" data-label="Severity">
                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-md"
                      :class="severityClass(rec.times_late)">
                  {{ severityLabel(rec.times_late) }}
                </span>
              </td>
              <td class="td-cell text-center font-mono font-semibold" data-label="Times Late" :class="tardyColor(rec.times_late)">
                {{ rec.times_late }}×
              </td>
              <td class="td-cell text-center font-mono" data-label="Total (min)" :class="tardyColor(rec.times_late)">
                {{ rec.total_minutes }}
              </td>
              <td class="td-cell text-center font-mono text-[#64748B]" data-label="Avg (min)">{{ rec.avg_minutes }}</td>
              <td class="td-cell text-center font-mono text-[#64748B]" data-label="Worst (min)">{{ rec.max_minutes }}</td>
              <td class="td-cell" data-label="Recent Dates">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="d in rec.late_dates" :key="d"
                    class="inline-flex px-1.5 py-0.5 text-[10px] font-mono rounded bg-amber-50 text-amber-700 border border-amber-200"
                  >{{ shortDate(d) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
      </template>

      </template>

      <!-- ═══════════════════════ BRADFORD FACTOR TAB ═══════════════════════ -->
      <template v-else>

      <!-- Filters -->
      <div class="bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="filter-label">Period</label>
          <select v-model.number="bfFilters.days" class="filter-input">
            <option :value="30">Last 30 Days</option>
            <option :value="90">Last 90 Days</option>
            <option :value="180">Last 6 Months</option>
            <option :value="365">Last 12 Months</option>
          </select>
        </div>
        <div v-if="auth.isAdmin || auth.isHrOfficer">
          <label class="filter-label">Department</label>
          <select v-model="bfFilters.department_id" class="filter-input w-44">
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="filter-label">Search</label>
          <input v-model="bfFilters.search" type="text" placeholder="Name or ID…" class="filter-input w-44" />
        </div>
        <button @click="loadBradford" :disabled="bfLoading" class="refresh-btn ml-auto">
          <Loader2Icon v-if="bfLoading" class="w-4 h-4 animate-spin" />
          <RefreshCwIcon v-else class="w-4 h-4" />
          Refresh
        </button>
        <button
          v-if="bfRecords.length"
          @click="exportBradfordCsv"
          class="inline-flex items-center gap-2 px-4 py-1.5 bg-white border border-[#E2E8F0] text-[#374151] text-sm font-medium rounded-lg hover:bg-[#F8FAFC] transition-colors export-btn"
        >
          <DownloadIcon class="w-4 h-4" />
          Export CSV
        </button>
      </div>

      <!-- Explainer -->
      <div class="bf-note">
        <InfoIcon class="w-4 h-4 flex-shrink-0" />
        <span>
          <strong>Bradford Factor</strong> = (absence instances)&sup2; &times; total absence days. Frequent,
          scattered short absences score higher than one long continuous absence covering the same days —
          this surfaces a disruptive pattern that a plain absence count would miss. Informational only;
          it does not trigger any automatic action.
        </span>
      </div>

      <!-- Summary cards -->
      <div v-if="bfRecords.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-for="s in bfSummary" :key="s.label" class="stat-tile" :style="statVars(s.color)">
          <div class="stat-tile-icon">
            <component :is="s.icon" class="w-5 h-5" />
          </div>
          <div>
            <p class="stat-tile-value">{{ s.value }}</p>
            <p class="stat-tile-label">{{ s.label }}</p>
          </div>
        </div>
      </div>

      <!-- Skeleton -->
      <div v-if="bfLoading" class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-4 py-4 border-b border-[#E2E8F0] last:border-0">
          <div class="w-8 h-8 rounded-full bg-slate-200 animate-pulse"></div>
          <div class="flex-1 space-y-1.5">
            <div class="h-3 w-36 rounded bg-slate-200 animate-pulse"></div>
            <div class="h-2.5 w-24 rounded bg-slate-200 animate-pulse"></div>
          </div>
          <div class="h-6 w-16 rounded-md bg-slate-200 animate-pulse"></div>
          <div class="h-6 w-20 rounded-md bg-slate-200 animate-pulse"></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="!bfRecords.length" class="bg-white border border-[#E2E8F0] rounded-xl flex flex-col items-center justify-center h-48 text-[#6B7A94]">
        <CheckCircleIcon class="w-10 h-10 mb-2 opacity-30 text-emerald-500" />
        <p class="text-sm font-medium text-[#64748B]">No flagged absence patterns</p>
        <p class="text-xs mt-1">No one has more than one absence instance this period.</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
        <div class="tk-table-scroll">
        <table class="w-full text-sm tk-tardy-table">
          <thead class="border-b border-[#E2E8F0]">
            <tr>
              <th class="th-cell text-left w-10">#</th>
              <th class="th-cell text-left">Employee</th>
              <th class="th-cell text-center">Severity</th>
              <th class="th-cell text-center font-mono">Instances</th>
              <th class="th-cell text-center font-mono">Total Days</th>
              <th class="th-cell text-center font-mono">Bradford Score</th>
              <th class="th-cell text-left">Recent Absences</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(rec, idx) in bfRecords" :key="rec.employee_id"
              class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#FAFBFF] transition-colors"
            >
              <td class="td-cell text-[#6B7A94] font-mono text-xs" data-label="Rank">{{ idx + 1 }}</td>
              <td class="td-cell tk-emp-cell">
                <div class="flex items-center gap-2">
                  <div
                    class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                    :style="bfAvatarGradient(rec.bradford_score)"
                  >{{ initials(rec.name) }}</div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ rec.name }}</p>
                    <p class="text-xs text-[#6B7A94]">{{ rec.department }}</p>
                  </div>
                </div>
              </td>
              <td class="td-cell text-center" data-label="Severity">
                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-md"
                      :class="bfSeverityClass(rec.bradford_score)">
                  {{ bfSeverityLabel(rec.bradford_score) }}
                </span>
              </td>
              <td class="td-cell text-center font-mono" data-label="Instances">{{ rec.instances }}</td>
              <td class="td-cell text-center font-mono" data-label="Total Days">{{ rec.total_days }}</td>
              <td class="td-cell text-center font-mono font-semibold" data-label="Bradford Score" :class="bfColor(rec.bradford_score)">
                {{ rec.bradford_score }}
              </td>
              <td class="td-cell" data-label="Recent Absences">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="d in rec.absence_dates" :key="d"
                    class="inline-flex px-1.5 py-0.5 text-[10px] font-mono rounded bg-red-50 text-red-700 border border-red-200"
                  >{{ shortDate(d) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { useCountUp } from '@/composables/useCountUp';
import PageHeader from '@/components/PageHeader.vue';
import {
  Chart, BarController, DoughnutController, CategoryScale, LinearScale, BarElement, ArcElement, Tooltip,
} from 'chart.js';

Chart.register(BarController, DoughnutController, CategoryScale, LinearScale, BarElement, ArcElement, Tooltip);
import {
  Loader2     as Loader2Icon,
  RefreshCw   as RefreshCwIcon,
  CheckCircle as CheckCircleIcon,
  AlertCircle as AlertCircleIcon,
  Clock       as ClockIcon,
  Users       as UsersIcon,
  BarChart2   as BarChart2Icon,
  Download    as DownloadIcon,
  Printer     as PrinterIcon,
  UserX       as UserXIcon,
  Info        as InfoIcon,
  Repeat      as RepeatIcon,
} from 'lucide-vue-next';

const auth  = useAuthStore();
const theme = useThemeStore();

const activeTab   = ref('tardiness');
const departments = ref([]);

const now    = new Date();
const months = [
  { value: 1,  label: 'January'   }, { value: 2,  label: 'February'  },
  { value: 3,  label: 'March'     }, { value: 4,  label: 'April'     },
  { value: 5,  label: 'May'       }, { value: 6,  label: 'June'      },
  { value: 7,  label: 'July'      }, { value: 8,  label: 'August'    },
  { value: 9,  label: 'September' }, { value: 10, label: 'October'   },
  { value: 11, label: 'November'  }, { value: 12, label: 'December'  },
];
const years = Array.from({ length: 6 }, (_, i) => now.getFullYear() - i);

const tardyRecords = ref([]);
const tardyLoading = ref(false);
const tardyFilters = reactive({
  month:         now.getMonth() + 1,
  year:          now.getFullYear(),
  department_id: '',
  search:        '',
});

const tardyEmployeesLateRaw = computed(() => tardyRecords.value.length);
const tardyTotalMinsRaw     = computed(() => tardyRecords.value.reduce((s, r) => s + r.total_minutes, 0));
const tardyAvgMinsRaw       = computed(() => {
  const totalInstances = tardyRecords.value.reduce((s, r) => s + r.times_late, 0);
  return totalInstances ? Math.round(tardyTotalMinsRaw.value / totalInstances) : 0;
});

// Animated count-up display values — purely cosmetic, the raw computeds above
// still drive the chart/severity logic.
const tardyEmployeesLateAnim = useCountUp(tardyEmployeesLateRaw);
const tardyTotalMinsAnim     = useCountUp(tardyTotalMinsRaw);
const tardyAvgMinsAnim       = useCountUp(tardyAvgMinsRaw);

const tardySummary = computed(() => {
  if (!tardyRecords.value.length) return [];
  return [
    { label: 'Employees Late',   value: tardyEmployeesLateAnim.value, icon: UsersIcon,       accent: '#D97706', wash: 'linear-gradient(135deg, #FFFBEB 0%, #FFF7E6 100%)' },
    { label: 'Total Late (min)', value: tardyTotalMinsAnim.value,     icon: ClockIcon,       accent: '#DC2626', wash: 'linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%)' },
    { label: 'Avg per Incident', value: tardyAvgMinsAnim.value,       icon: AlertCircleIcon, accent: '#EA580C', wash: 'linear-gradient(135deg, #FFF7ED 0%, #FFFBF5 100%)' },
  ];
});

const severityCounts = computed(() => {
  const counts = { low: 0, moderate: 0, high: 0 };
  for (const r of tardyRecords.value) {
    if (r.times_late >= 6) counts.high++;
    else if (r.times_late >= 3) counts.moderate++;
    else counts.low++;
  }
  return counts;
});

async function loadTardiness() {
  tardyLoading.value = true;
  try {
    const params = { month: tardyFilters.month, year: tardyFilters.year };
    if (tardyFilters.department_id) params.department_id = tardyFilters.department_id;
    if (tardyFilters.search)        params.search        = tardyFilters.search;
    const res = await axios.get('/api/tracking/tardiness', { params });
    tardyRecords.value = res.data;
  } catch {
    tardyRecords.value = [];
  } finally {
    tardyLoading.value = false;
  }
}

// ── Leaderboard chart — top 8 most-tardy employees this period ─────
// Ranked by total minutes late, matching the table below (the backend
// already sorts tardyRecords this way) — keeping both views in the same
// order so the chart never contradicts the table underneath it.
const tardyChartCanvas    = ref(null);
const severityChartCanvas = ref(null);
let tardyChart    = null;
let severityChart = null;

function severityBand(times) {
  if (times >= 6) return { key: 'high',     color: '#DC2626', light: '#F87171' };
  if (times >= 3) return { key: 'moderate', color: '#D97706', light: '#FBBF24' };
  return { key: 'low', color: '#2E4A8C', light: '#3D5CA3' };
}

// Draws the actual minute value just past the tip of each horizontal bar —
// so the ranking reads at a glance without needing to hover for a tooltip.
// `bar.x` is Chart.js's own animated/interpolated tip position, so drawing
// against it every frame makes the label track the bar as it grows in,
// with no extra progress-tracking needed on our end.
const barValueLabels = {
  id: 'barValueLabels',
  afterDatasetsDraw(chart) {
    if (chart.config.type !== 'bar') return;
    const { ctx } = chart;
    const meta = chart.getDatasetMeta(0);
    if (!meta?.data?.length) return;
    const isDark = theme.isDark;
    ctx.save();
    ctx.font = '600 11px system-ui, -apple-system, Arial, sans-serif';
    ctx.fillStyle = isDark ? '#E2E8F0' : '#374151';
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    meta.data.forEach((bar, i) => {
      const value = chart.data.datasets[0].data[i];
      ctx.fillText(`${value} min`, bar.x + 8, bar.y);
    });
    ctx.restore();
  },
};

function buildTardyChart(records) {
  if (tardyChart) { tardyChart.destroy(); tardyChart = null; }
  if (!tardyChartCanvas.value || !records.length) return;

  const top = [...records].sort((a, b) => b.total_minutes - a.total_minutes).slice(0, 8);
  const isDark    = theme.isDark;
  const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(226,232,240,0.65)';
  const tickColor = isDark ? '#94A3B8' : '#374151';

  // Horizontal gradient per bar — lighter at the base, deepening toward the
  // tip — so the ranking reads with a bit more depth than a flat fill.
  // Chart.js only invokes a *single* scriptable function for backgroundColor;
  // an array of functions is treated as literal (invalid) color values, so
  // this has to branch on ctx.dataIndex rather than map() to one-per-bar.
  const gradientFill = (ctx) => {
    const rec = top[ctx.dataIndex];
    if (!rec) return '#2E4A8C';
    const band = severityBand(rec.times_late);
    const { chart } = ctx;
    const { chartArea } = chart;
    if (!chartArea) return band.color;
    const grad = chart.ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
    grad.addColorStop(0, band.light);
    grad.addColorStop(1, band.color);
    return grad;
  };
  const hoverGradientFill = (ctx) => {
    const rec = top[ctx.dataIndex];
    if (!rec) return '#3D5CA3';
    const band = severityBand(rec.times_late);
    const { chart } = ctx;
    const { chartArea } = chart;
    if (!chartArea) return band.light;
    const grad = chart.ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
    grad.addColorStop(0, band.color);
    grad.addColorStop(1, band.light);
    return grad;
  };

  tardyChart = new Chart(tardyChartCanvas.value, {
    type: 'bar',
    data: {
      labels: top.map(r => r.name),
      datasets: [{
        data: top.map(r => r.total_minutes),
        backgroundColor: gradientFill,
        hoverBackgroundColor: hoverGradientFill,
        borderRadius: 8,
        borderSkipped: false,
        maxBarThickness: 20,
        categoryPercentage: 0.7,
        barPercentage: 0.9,
      }],
    },
    plugins: [barValueLabels],
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { right: 44 } },
      // Cascading reveal — each bar starts slightly after the one above it,
      // instead of every bar growing in lockstep, for a smoother "poured in"
      // feel.
      animation: {
        duration: 650,
        easing: 'easeOutQuart',
        delay: (ctx) => ctx.type === 'data' && ctx.mode === 'default' ? ctx.dataIndex * 70 : 0,
      },
      hover: { animationDuration: 150 },
      interaction: { mode: 'nearest', axis: 'y', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          padding: 12, cornerRadius: 8,
          backgroundColor: '#1A2444',
          borderColor: 'rgba(201, 162, 39,0.45)', borderWidth: 1,
          titleFont: { weight: '600' },
          callbacks: {
            title: items => top[items[0].dataIndex].name,
            label: ctx => {
              const rec = top[ctx.dataIndex];
              return [
                `${rec.total_minutes} min total late`,
                `${rec.times_late}× late · ${rec.avg_minutes} min avg · ${rec.max_minutes} min worst`,
              ];
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: { color: tickColor, precision: 0 },
          grid: { color: gridColor, drawTicks: false },
          border: { display: false },
        },
        y: {
          ticks: { color: tickColor, font: { size: 11 } },
          grid: { display: false },
          border: { display: false },
        },
      },
    },
  });
}

// Center-text plugin — draws the total employee count in the doughnut's hole,
// so the chart doubles as a KPI, not just a proportion breakdown.
const donutCenterText = {
  id: 'donutCenterText',
  afterDraw(chart) {
    if (chart.config.type !== 'doughnut') return;
    const { ctx, chartArea } = chart;
    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
    const cx = (chartArea.left + chartArea.right) / 2;
    const cy = (chartArea.top + chartArea.bottom) / 2;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = theme.isDark ? '#E2E8F0' : '#0F172A';
    ctx.font = '700 22px system-ui, -apple-system, Arial, sans-serif';
    ctx.fillText(String(total), cx, cy - 8);
    ctx.fillStyle = theme.isDark ? '#94A3B8' : '#6B7A94';
    ctx.font = '600 10px system-ui, -apple-system, Arial, sans-serif';
    ctx.fillText('EMPLOYEES', cx, cy + 12);
    ctx.restore();
  },
};

function buildSeverityChart(records) {
  if (severityChart) { severityChart.destroy(); severityChart = null; }
  if (!severityChartCanvas.value || !records.length) return;

  const counts = severityCounts.value;
  const isDark = theme.isDark;

  severityChart = new Chart(severityChartCanvas.value, {
    type: 'doughnut',
    data: {
      labels: ['Low', 'Moderate', 'High'],
      datasets: [{
        data: [counts.low, counts.moderate, counts.high],
        backgroundColor: ['#2E4A8C', '#D97706', '#DC2626'],
        borderColor: isDark ? '#1C2A44' : '#fff',
        borderWidth: 3,
        hoverOffset: 10,
        hoverBorderWidth: 4,
      }],
    },
    plugins: [donutCenterText],
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      // Same cascading feel as the leaderboard bars — each wedge sweeps in
      // slightly after the previous one rather than all three at once.
      animation: {
        duration: 650,
        easing: 'easeOutQuart',
        delay: (ctx) => ctx.type === 'data' && ctx.mode === 'default' ? ctx.dataIndex * 90 : 0,
      },
      hover: { animationDuration: 150 },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 10, boxHeight: 10, padding: 14,
            color: isDark ? '#94A3B8' : '#6B7A94',
            font: { size: 11, weight: '600' },
          },
        },
        tooltip: {
          padding: 10, cornerRadius: 8,
          backgroundColor: '#1A2444',
          borderColor: 'rgba(201, 162, 39,0.45)', borderWidth: 1,
          callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} employee(s)` },
        },
      },
    },
  });
}

watch(tardyRecords, (records) => {
  nextTick(() => { buildTardyChart(records); buildSeverityChart(records); });
});
watch(() => theme.isDark, () => {
  buildTardyChart(tardyRecords.value);
  buildSeverityChart(tardyRecords.value);
});

onBeforeUnmount(() => { tardyChart?.destroy(); severityChart?.destroy(); });

watch([() => tardyFilters.month, () => tardyFilters.year, () => tardyFilters.department_id], loadTardiness);

// ── Bradford Factor ──────────────────────────────────────────────
const bfRecords = ref([]);
const bfLoading = ref(false);
const bfFilters = reactive({
  days:          90,
  department_id: '',
  search:        '',
});

const bfSummary = computed(() => {
  if (!bfRecords.value.length) return [];
  const totalInstances = bfRecords.value.reduce((s, r) => s + r.instances, 0);
  const highest         = Math.max(...bfRecords.value.map(r => r.bradford_score));
  return [
    { label: 'Employees Flagged',  value: bfRecords.value.length, icon: UserXIcon,       color: '#DC2626' },
    { label: 'Absence Instances',  value: totalInstances,         icon: RepeatIcon,      color: '#EA580C' },
    { label: 'Highest Score',      value: highest,                icon: AlertCircleIcon, color: '#D97706' },
  ];
});

function statVars(hex) {
  return { '--stat-color': hex, '--stat-bg': hex + '14', '--stat-glow': hex + '30' };
}

async function loadBradford() {
  bfLoading.value = true;
  try {
    const params = { days: bfFilters.days };
    if (bfFilters.department_id) params.department_id = bfFilters.department_id;
    if (bfFilters.search)        params.search        = bfFilters.search;
    const res = await axios.get('/api/tracking/bradford-factor', { params });
    bfRecords.value = res.data;
  } catch {
    bfRecords.value = [];
  } finally {
    bfLoading.value = false;
  }
}

watch([() => bfFilters.days, () => bfFilters.department_id], loadBradford);
watch(activeTab, (tab) => {
  if (tab === 'bradford') {
    if (!bfRecords.value.length) loadBradford();
    // The canvases are removed from the DOM by v-if when leaving this tab —
    // destroy the Chart.js instances now rather than leaving them holding
    // onto detached elements until the whole view unmounts.
    tardyChart?.destroy();    tardyChart = null;
    severityChart?.destroy(); severityChart = null;
  }
  // The tardiness tab's canvas is destroyed/recreated by v-if when switching
  // tabs — the Chart.js instance needs rebuilding against the new element.
  if (tab === 'tardiness') nextTick(() => {
    buildTardyChart(tardyRecords.value);
    buildSeverityChart(tardyRecords.value);
  });
});

function initials(name) {
  return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() ?? '?';
}

function shortDate(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function tardyColor(times) {
  if (times >= 6) return 'text-red-600';
  if (times >= 3) return 'text-amber-600';
  return 'text-[#64748B]';
}

function severityClass(times) {
  if (times >= 6) return 'bg-red-100 text-red-700';
  if (times >= 3) return 'bg-amber-100 text-amber-700';
  return 'bg-slate-100 text-slate-600';
}

function severityLabel(times) {
  if (times >= 6) return 'High';
  if (times >= 3) return 'Moderate';
  return 'Low';
}

function avatarGradient(times) {
  if (times >= 6) return 'background: linear-gradient(135deg, #DC2626, #991B1B);';
  if (times >= 3) return 'background: linear-gradient(135deg, #D97706, #B45309);';
  return 'background: linear-gradient(135deg, #2E4A8C, #3D5CA3);';
}

// Bradford Factor severity bands are informational labels only — they
// do not gate or trigger any action in the system.
function bfColor(score) {
  if (score >= 450) return 'text-red-600';
  if (score >= 200) return 'text-orange-600';
  if (score >= 50)  return 'text-amber-600';
  return 'text-[#64748B]';
}

function bfSeverityClass(score) {
  if (score >= 450) return 'bg-red-100 text-red-700';
  if (score >= 200) return 'bg-orange-100 text-orange-700';
  if (score >= 50)  return 'bg-amber-100 text-amber-700';
  return 'bg-slate-100 text-slate-600';
}

function bfSeverityLabel(score) {
  if (score >= 450) return 'High';
  if (score >= 200) return 'Elevated';
  if (score >= 50)  return 'Watch';
  return 'Low';
}

function bfAvatarGradient(score) {
  if (score >= 450) return 'background: linear-gradient(135deg, #DC2626, #991B1B);';
  if (score >= 200) return 'background: linear-gradient(135deg, #EA580C, #C2410C);';
  if (score >= 50)  return 'background: linear-gradient(135deg, #D97706, #B45309);';
  return 'background: linear-gradient(135deg, #2E4A8C, #3D5CA3);';
}

function exportBradfordCsv() {
  const periodLabel = { 30: 'Last 30 Days', 90: 'Last 90 Days', 180: 'Last 6 Months', 365: 'Last 12 Months' }[bfFilters.days] ?? `${bfFilters.days} Days`;
  const rows = [
    ['NEMSU — Habitual Absence (Bradford Factor) Report'],
    [`Period: ${periodLabel}`],
    [],
    ['#', 'Employee Name', 'Department', 'Instances', 'Total Days', 'Bradford Score', 'Recent Absences'],
    ...bfRecords.value.map((r, i) => [
      i + 1,
      r.name,
      r.department ?? '',
      r.instances,
      r.total_days,
      r.bradford_score,
      r.absence_dates?.join(', ') ?? '',
    ]),
  ];

  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `Habitual_Absence_${periodLabel.replace(/\s+/g, '_')}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

function exportTardyCsv() {
  const monthName = months.find(m => m.value === tardyFilters.month)?.label ?? tardyFilters.month;
  const rows = [
    ['NEMSU — Tardiness Tracking Report'],
    [`Period: ${monthName} ${tardyFilters.year}`],
    [],
    ['#', 'Employee Name', 'Department', 'Times Late', 'Total (min)', 'Average (min)', 'Worst (min)', 'Late Dates'],
    ...tardyRecords.value.map((r, i) => [
      i + 1,
      r.name,
      r.department ?? '',
      r.times_late,
      r.total_minutes,
      r.avg_minutes,
      r.max_minutes,
      r.late_dates?.join(', ') ?? '',
    ]),
  ];

  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `Tardiness_${monthName}_${tardyFilters.year}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

function printReport() {
  window.print();
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/departments');
    departments.value = res.data;
  } catch { /* departments stays empty */ }
  await loadTardiness();
});
</script>

<style scoped>
@reference "../css/app.css";

/* ── Tabs ────────────────────────────────────────────────────── */
.tab-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 16px; font-size: 13px; font-weight: 600;
  color: #64748B; border-radius: 8px; border: none;
  background: transparent; cursor: pointer; transition: all 0.15s;
}
.tab-btn:hover { background: #F4F6FB; color: #374151; }
.tab-btn--active { background: #2E4A8C; color: #fff; }
.tab-btn--active:hover { background: #2E4A8C; color: #fff; }

/* ── Bradford Factor explainer ───────────────────────────────── */
.bf-note {
  display: flex; align-items: flex-start; gap: 10px;
  background: #EFF4FF; border: 1px solid #DCE6FF; border-radius: 10px;
  padding: 12px 14px; font-size: 12.5px; line-height: 1.5; color: #3B4A78;
}
.bf-note strong { color: #2E4A8C; }

/* ── Filter bar ──────────────────────────────────────────────── */
.filter-label { @apply block text-[10px] font-semibold uppercase tracking-wider mb-1; color: #6B7A94; }
.filter-input {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 6px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-input:focus { outline: none; border-color: #2E4A8C; box-shadow: 0 0 0 3px rgba(46, 74, 140,0.08); }

.refresh-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 16px; background: #2E4A8C; color: #fff;
  font-size: 13px; font-weight: 600; border-radius: 8px;
  border: none; cursor: pointer; transition: background 0.15s;
}
.refresh-btn:hover:not(:disabled) { background: #3D5CA3; }
.refresh-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.export-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 14px; background: #fff; color: #374151;
  font-size: 13px; font-weight: 500; border-radius: 8px;
  border: 1px solid #E4E9F2; cursor: pointer; transition: all 0.15s;
}
.export-btn:hover { background: #F4F6FB; border-color: #C9D4E8; }

/* ── Summary stat cards ──────────────────────────────────────── */
.stat-cards  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.stat-card-t {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  padding: 16px 18px; display: flex; align-items: center; gap: 14px;
  box-shadow: 0 1px 4px rgba(27, 42, 94,0.05);
  transition: box-shadow 0.2s, transform 0.2s;
}
.stat-card-t:hover { box-shadow: 0 4px 16px rgba(27, 42, 94,0.09); transform: translateY(-1px); }
.stat-icon  {
  width: 42px; height: 42px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-val   { font-size: 24px; font-weight: 700; color: #0F172A; line-height: 1; }
.stat-lbl   { font-size: 11px; color: #6B7A94; margin-top: 3px; }

/* ── Data table ──────────────────────────────────────────────── */
.track-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(27, 42, 94,0.06), 0 1px 3px rgba(27, 42, 94,0.04);
}
.track-scroll { overflow-x: auto; }

/* Horizontal scroll for the leaderboard / Bradford tables instead of
   letting long department names wrap and blow up row height. */
.tk-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.tk-tardy-table   { min-width: 760px; }
.tk-tardy-table .td-cell p { white-space: nowrap; }

.th-cell {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #64748B; background: #F7F8FD; white-space: nowrap;
}
.td-cell { padding: 12px 16px; }
.track-row {
  border-bottom: 1px solid #F1F5F9;
  transition: background 0.12s;
}
.track-row:last-child { border-bottom: none; }
.track-row:hover { background: #F8FAFF; }

/* ── Rank chip ───────────────────────────────────────────────── */
.rank-chip {
  width: 24px; height: 24px; border-radius: 6px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; font-family: monospace;
  background: #EEF2FF; color: #4B5E8A;
}

/* ── Empty / skeleton ────────────────────────────────────────── */
.empty-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; min-height: 180px; gap: 8px;
}

/* ── Mobile ──────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .stat-cards { grid-template-columns: 1fr 1fr; gap: 10px; }
  .track-scroll { overflow-x: auto; }
}
@media (max-width: 480px) {
  .stat-cards { grid-template-columns: 1fr; }
}

/* ── Leaderboard / Bradford tables → stacked cards on mobile ─────────
   Same reasoning as Attendance: a 7-8 column table needing a sideways
   scroll on a phone is worse than just stacking each row as its own
   card, using each cell's data-label in place of a column header. */
@media (max-width: 640px) {
  .tk-table-scroll { overflow-x: visible; }
  .tk-tardy-table  { display: block; min-width: 0; width: 100%; }
  .tk-tardy-table tbody { display: block; }
  .tk-tardy-table .td-cell p { white-space: normal; }

  .tk-tardy-table thead { display: none; }

  .tk-tardy-table tbody tr {
    display: block;
    margin-bottom: 10px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 10px 12px;
  }

  .tk-tardy-table td {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    text-align: left !important;
    padding: 5px 0;
    border: none !important;
  }

  .tk-tardy-table td[data-label]::before {
    content: attr(data-label);
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #94A3B8;
  }

  /* Rank (first, has data-label="Rank") stays a normal label/value row;
     the Employee cell right after it becomes the card's full-width header. */
  .tk-tardy-table .tk-emp-cell {
    display: block;
    padding: 6px 0 8px;
    margin-bottom: 4px;
    border-bottom: 1px solid #F1F5F9 !important;
  }

  [data-theme="dark"] .tk-tardy-table tbody tr {
    border-color: rgba(255,255,255,0.08) !important;
  }
  [data-theme="dark"] .tk-tardy-table .tk-emp-cell {
    border-bottom-color: rgba(255,255,255,0.08) !important;
  }
}

/* ── Print ───────────────────────────────────────────────────── */
@media print {
  .no-print { display: none !important; }
  body { background: #fff !important; }
  .track-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  .track-row:hover { background: transparent !important; }
}

/* ── Tardiness leaderboard chart ─────────────────────────────────── */
.chart-panel {
  background: #fff;
  border: 1px solid #EEF2FA;
  border-top: 3px solid #C9A227;
  border-radius: 10px;
  overflow: hidden;
}
.cp-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px; border-bottom: 1px solid #F4F6FB; gap: 12px;
}
.cp-title { font-size: 13px; font-weight: 700; color: #0F172A; margin: 0; }
.cp-sub   { font-size: 11px; color: #6B7A94; margin: 2px 0 0; }
.tardy-chart-body { padding: 18px; height: 280px; }

/* ── Analytics row: leaderboard + severity donut side by side ────── */
.tk-analytics-row { display: flex; gap: 16px; align-items: stretch; }
.tk-leaderboard-panel { flex: 1 1 65%; min-width: 0; }
.tk-severity-panel    { flex: 1 1 35%; min-width: 260px; }
.tardy-donut-body { padding: 14px 18px 18px; height: 280px; }
@media (max-width: 900px) {
  .tk-analytics-row { flex-direction: column; }
  .tk-severity-panel { min-width: 0; }
}

/* ── Animated summary stat cards ──────────────────────────────────── */
.tk-stat-card {
  position: relative;
  border: 1px solid #E2E8F0;
  border-radius: 12px;
  padding: 16px 18px 16px 20px;
  overflow: hidden;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.tk-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(8,14,80,0.08);
}
.tk-stat-accent {
  position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
}
.tk-stat-icon {
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.tk-stat-value { font-size: 26px; font-weight: 800; color: #0F172A; line-height: 1; letter-spacing: -0.01em; }
.tk-stat-label { font-size: 11.5px; color: #64748B; margin-top: 4px; font-weight: 500; }

/* ── Leaderboard medal badges ─────────────────────────────────────── */
.tk-medal {
  display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%;
  font-weight: 800; font-size: 10.5px; color: #fff;
}
.tk-medal--0 { background: linear-gradient(135deg, #F6D776, #C9A227); box-shadow: 0 0 0 2px rgba(201, 162, 39,0.18); }
.tk-medal--1 { background: linear-gradient(135deg, #D7DEE8, #9AA7B8); box-shadow: 0 0 0 2px rgba(154,167,184,0.18); }
.tk-medal--2 { background: linear-gradient(135deg, #D89A6A, #B06A3A); box-shadow: 0 0 0 2px rgba(176,106,58,0.18); }

/* ── Dark mode ─────────────────────────────────────────────────────── */
[data-theme="dark"] .tk-stat-card { border-color: rgba(255,255,255,0.08); background: #1C2A44 !important; }
[data-theme="dark"] .tk-stat-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.35); }
[data-theme="dark"] .tk-stat-value { color: #E2E8F0; }
[data-theme="dark"] .tk-stat-label { color: #9AAAC9; }
</style>
