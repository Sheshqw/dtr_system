<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Tracking" subtitle="Employee tardiness analytics" :icon="BarChart2Icon" />

    <div class="flex-1 overflow-auto bg-[#F4F6FB] p-6 space-y-4">

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
          @click="exportCsv"
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
      <div v-if="tardyRecords.length" class="grid grid-cols-3 gap-4">
        <div v-for="s in tardySummary" :key="s.label"
             class="bg-white border border-[#E2E8F0] rounded-xl px-5 py-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="s.iconBg">
            <component :is="s.icon" class="w-5 h-5" :class="s.iconColor" />
          </div>
          <div>
            <p class="text-2xl font-semibold text-[#0F172A] leading-none">{{ s.value }}</p>
            <p class="text-xs text-[#64748B] mt-0.5">{{ s.label }}</p>
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
      <div v-else-if="!tardyRecords.length" class="bg-white border border-[#E2E8F0] rounded-xl flex flex-col items-center justify-center h-48 text-[#94A3B8]">
        <CheckCircleIcon class="w-10 h-10 mb-2 opacity-30 text-emerald-500" />
        <p class="text-sm font-medium text-[#64748B]">No tardiness records</p>
        <p class="text-xs mt-1">Everyone was on time this period!</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
        <table class="w-full text-sm">
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
              <td class="td-cell text-[#94A3B8] font-mono text-xs">{{ idx + 1 }}</td>
              <td class="td-cell">
                <div class="flex items-center gap-2">
                  <div
                    class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                    :style="avatarGradient(rec.times_late)"
                  >{{ initials(rec.name) }}</div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ rec.name }}</p>
                    <p class="text-xs text-[#94A3B8]">{{ rec.department }}</p>
                  </div>
                </div>
              </td>
              <td class="td-cell text-center">
                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-md"
                      :class="severityClass(rec.times_late)">
                  {{ severityLabel(rec.times_late) }}
                </span>
              </td>
              <td class="td-cell text-center font-mono font-semibold" :class="tardyColor(rec.times_late)">
                {{ rec.times_late }}×
              </td>
              <td class="td-cell text-center font-mono" :class="tardyColor(rec.times_late)">
                {{ rec.total_minutes }}
              </td>
              <td class="td-cell text-center font-mono text-[#64748B]">{{ rec.avg_minutes }}</td>
              <td class="td-cell text-center font-mono text-[#64748B]">{{ rec.max_minutes }}</td>
              <td class="td-cell">
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
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import PageHeader from '@/components/PageHeader.vue';
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
} from 'lucide-vue-next';

const auth = useAuthStore();

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

const tardySummary = computed(() => {
  if (!tardyRecords.value.length) return [];
  const totalInstances = tardyRecords.value.reduce((s, r) => s + r.times_late, 0);
  const totalMins      = tardyRecords.value.reduce((s, r) => s + r.total_minutes, 0);
  const avgMins        = totalInstances ? Math.round(totalMins / totalInstances) : 0;
  return [
    { label: 'Employees Late',   value: tardyRecords.value.length, icon: UsersIcon,       iconBg: 'bg-amber-50',  iconColor: 'text-amber-600'  },
    { label: 'Total Late (min)', value: totalMins,                 icon: ClockIcon,       iconBg: 'bg-red-50',    iconColor: 'text-red-500'    },
    { label: 'Avg per Incident', value: avgMins,                   icon: AlertCircleIcon, iconBg: 'bg-orange-50', iconColor: 'text-orange-500' },
  ];
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

watch([() => tardyFilters.month, () => tardyFilters.year, () => tardyFilters.department_id], loadTardiness);

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
  return 'background: linear-gradient(135deg, #0E1EC0, #1228CC);';
}

function exportCsv() {
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

/* ── Filter bar ──────────────────────────────────────────────── */
.filter-label { @apply block text-[10px] font-semibold uppercase tracking-wider mb-1; color: #7B8FBF; }
.filter-input {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 6px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.filter-input:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

.refresh-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 16px; background: #0E1EC0; color: #fff;
  font-size: 13px; font-weight: 600; border-radius: 8px;
  border: none; cursor: pointer; transition: background 0.15s;
}
.refresh-btn:hover:not(:disabled) { background: #1228CC; }
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
  box-shadow: 0 1px 4px rgba(8,14,128,0.05);
  transition: box-shadow 0.2s, transform 0.2s;
}
.stat-card-t:hover { box-shadow: 0 4px 16px rgba(8,14,128,0.09); transform: translateY(-1px); }
.stat-icon  {
  width: 42px; height: 42px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-val   { font-size: 24px; font-weight: 700; color: #0F172A; line-height: 1; }
.stat-lbl   { font-size: 11px; color: #7B8FBF; margin-top: 3px; }

/* ── Data table ──────────────────────────────────────────────── */
.track-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(8,14,128,0.06), 0 1px 3px rgba(8,14,128,0.04);
}
.track-scroll { overflow-x: auto; }
.th-cell {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #5A6899; background: #F7F8FD; white-space: nowrap;
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

/* ── Print ───────────────────────────────────────────────────── */
@media print {
  .no-print { display: none !important; }
  body { background: #fff !important; }
  .track-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  .track-row:hover { background: transparent !important; }
}
</style>
