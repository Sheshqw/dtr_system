<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Attendance Logs" subtitle="Punch records and daily time records" :icon="ClockIcon">
      <button
        v-if="(auth.isAdmin || auth.isHrOfficer) && activeTab === 'punches'"
        @click="openAddPunch"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors ph-btn-primary"
      >
        <PlusIcon class="w-4 h-4" />
        Add Manual Punch
      </button>
    </PageHeader>

    <!-- Tabs -->
    <div class="bg-white border-b border-[#E2E8F0] px-6">
      <div class="flex gap-6">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="py-3 text-sm font-medium border-b-2 transition-colors"
          :class="activeTab === tab.key
            ? 'border-[#C9A227] text-[#2E4A8C]'
            : 'border-transparent text-[#64748B] hover:text-[#0F172A]'"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div class="att-body flex-1 overflow-auto bg-[#F0F4FF] p-6">

      <!-- ── PUNCH LOGS TAB ── -->
      <div v-if="activeTab === 'punches'" class="space-y-4">
        <!-- Filters -->
        <div class="att-filters bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-wrap items-center gap-3">
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Date</label>
            <input
              v-model="punchFilters.date"
              type="date"
              class="filter-input"
            />
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Employee</label>
            <select v-model="punchFilters.employee_id" class="filter-input w-44">
              <option value="">All Employees</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Source</label>
            <select v-model="punchFilters.source" class="filter-input">
              <option value="">All Sources</option>
              <option value="biometric">Biometric</option>
              <option value="manual">Manual</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Type</label>
            <select v-model="punchFilters.punch_type" class="filter-input">
              <option value="">All Types</option>
              <option value="IN">IN</option>
              <option value="OUT">OUT</option>
            </select>
          </div>
          <div class="ml-auto flex items-end">
            <button @click="loadPunches" :disabled="punchLoading" class="refresh-btn">
              <Loader2Icon v-if="punchLoading" class="w-4 h-4 animate-spin" />
              <RefreshCwIcon v-else class="w-4 h-4" />
              Refresh
            </button>
          </div>
        </div>

        <!-- Stats strip -->
        <div v-if="punchLogs.length" class="grid grid-cols-4 gap-3">
          <div v-for="s in punchStats" :key="s.label" class="stat-tile" :style="statVars(s.color)">
            <div class="stat-tile-icon">
              <component :is="s.icon" class="w-5 h-5" />
            </div>
            <div>
              <p class="stat-tile-value">{{ s.value }}</p>
              <p class="stat-tile-label">{{ s.label }}</p>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
          <template v-if="punchLoading">
            <div v-for="i in 8" :key="'ps'+i" class="skel-row">
              <div class="flex items-center gap-2" style="flex:0 0 200px;">
                <div class="skel" style="width:28px;height:28px;border-radius:50%;flex-shrink:0;"></div>
                <div class="skel-lines">
                  <div class="skel skel-text" style="width:110px;"></div>
                  <div class="skel skel-text" style="width:64px;height:9px;"></div>
                </div>
              </div>
              <div class="skel skel-text" style="width:90px;"></div>
              <div class="skel skel-text" style="width:58px;"></div>
              <div class="skel skel-text" style="width:40px;border-radius:10px;"></div>
              <div class="skel skel-text" style="width:65px;border-radius:10px;"></div>
              <div class="skel skel-text" style="width:55px;"></div>
              <div class="skel skel-text" style="width:90px;flex:1;"></div>
            </div>
          </template>
          <div v-else-if="!punchLogs.length">
            <EmptyState title="No punch records" description="No biometric or manual punch records found for this date." />
          </div>
          <div v-else class="att-table-wrap overflow-x-auto">
          <table class="att-table w-full text-sm">
            <thead class="border-b border-[#E2E8F0]">
              <tr>
                <th class="th-cell text-left">Employee</th>
                <th class="th-cell text-left">Department</th>
                <th class="th-cell text-center">Date &amp; Time</th>
                <th class="th-cell text-center">Type</th>
                <th class="th-cell text-center">Source</th>
                <th class="th-cell text-left">Device</th>
                <th class="th-cell text-left">Remarks</th>
                <th v-if="auth.isAdmin || auth.isHrOfficer" class="th-cell text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(log, i) in filteredPunchLogs"
                :key="log.id"
                class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#F8FAFC] transition-colors row-enter"
                :class="[log.punch_type === 'IN' ? 'row-status--present' : 'row-status--absent', i % 2 === 1 ? 'bg-[#FAFBFF]' : 'bg-white']"
                :style="{ animationDelay: i * 20 + 'ms' }"
              >
                <td class="td-cell">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                         :style="avatarStyle(log.name)">
                      {{ initials(log.name) }}
                    </div>
                    <div>
                      <p class="font-medium text-[#0F172A] leading-tight">{{ log.name }}</p>
                      <p class="text-xs text-[#6B7A94] font-mono">{{ log.employee_id }}</p>
                    </div>
                  </div>
                </td>
                <td class="td-cell text-[#64748B] whitespace-nowrap">{{ log.department }}</td>
                <td class="td-cell text-center">
                  <p class="font-mono text-[#0F172A] font-medium leading-tight">{{ log.punch_time }}</p>
                  <p class="text-[10px] text-[#6B7A94] leading-tight">{{ formatLogDate(log) }}</p>
                </td>
                <td class="td-cell text-center">
                  <span :class="log.punch_type === 'IN' ? 'badge-in' : 'badge-out'">
                    {{ log.punch_type }}
                  </span>
                </td>
                <td class="td-cell text-center">
                  <span :class="log.source === 'biometric' ? 'badge-bio' : 'badge-manual'">
                    {{ log.source === 'biometric' ? 'Biometric' : 'Manual' }}
                  </span>
                </td>
                <td class="td-cell text-[#64748B] font-mono text-xs">{{ log.device_id ?? '—' }}</td>
                <td class="td-cell text-[#64748B] text-xs max-w-[160px] truncate">{{ log.remarks ?? '—' }}</td>
                <td v-if="auth.isAdmin || auth.isHrOfficer" class="td-cell text-center">
                  <button @click="confirmDelete(log)" class="p-1.5 rounded-md text-[#6B7A94] hover:text-[#EF4444] hover:bg-red-50 transition-colors">
                    <Trash2Icon class="w-4 h-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- ── DAILY RECORDS TAB ── -->
      <div v-if="activeTab === 'daily'" class="space-y-4">
        <!-- Filters -->
        <div class="rec-filters bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-wrap items-center gap-3">
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Month</label>
            <select v-model="dailyFilters.month" class="filter-input">
              <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Year</label>
            <select v-model="dailyFilters.year" class="filter-input">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div v-if="auth.isAdmin || auth.isHrOfficer">
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Department</label>
            <select v-model="dailyFilters.department_id" class="filter-input w-40">
              <option value="">All Departments</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div v-if="auth.isAdmin || auth.isHrOfficer || auth.isDeptHead">
            <label class="block text-[10px] font-semibold text-[#64748B] uppercase tracking-wide mb-1">Employee</label>
            <select v-model="dailyFilters.employee_id" class="filter-input w-44">
              <option value="">All Employees</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div class="ml-auto flex items-end">
            <button @click="loadDaily" :disabled="dailyLoading" class="refresh-btn">
              <Loader2Icon v-if="dailyLoading" class="w-4 h-4 animate-spin" />
              <RefreshCwIcon v-else class="w-4 h-4" />
              Refresh
            </button>
          </div>
        </div>

        <!-- Summary strip -->
        <div v-if="dailyRecords.length" class="grid grid-cols-4 gap-3">
          <div v-for="s in dailyStats" :key="s.label" class="stat-tile" :style="statVars(s.color)">
            <div class="stat-tile-icon">
              <component :is="s.icon" class="w-5 h-5" />
            </div>
            <div>
              <p class="stat-tile-value">{{ s.value }}</p>
              <p class="stat-tile-label">{{ s.label }}</p>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.06)] overflow-hidden">
          <template v-if="dailyLoading">
            <div v-for="i in 8" :key="'ds'+i" class="skel-row">
              <div class="flex items-center gap-2" style="flex:0 0 180px;">
                <div class="skel" style="width:24px;height:24px;border-radius:50%;flex-shrink:0;"></div>
                <div class="skel-lines">
                  <div class="skel skel-text" style="width:100px;"></div>
                  <div class="skel skel-text" style="width:56px;height:9px;"></div>
                </div>
              </div>
              <div class="skel skel-text" style="width:70px;"></div>
              <div class="skel skel-text" style="width:50px;"></div>
              <div class="skel skel-text" style="width:50px;"></div>
              <div class="skel skel-text" style="width:50px;"></div>
              <div class="skel skel-text" style="width:50px;"></div>
              <div class="skel skel-text" style="width:40px;"></div>
              <div class="skel skel-text" style="width:40px;"></div>
              <div class="skel skel-text" style="width:40px;"></div>
              <div class="skel skel-text" style="width:40px;"></div>
              <div class="skel skel-text" style="width:55px;border-radius:10px;"></div>
            </div>
          </template>
          <div v-else-if="!dailyRecords.length">
            <EmptyState title="No records found" description="No attendance records found for the selected period and filters." />
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm daily-table">
              <thead class="border-b border-[#E2E8F0]">
                <tr>
                  <th class="th-cell text-left sticky left-0 bg-white z-10 min-w-[180px]">Employee</th>
                  <th class="th-cell text-left min-w-[100px]">Date</th>
                  <th class="th-cell text-center font-mono">AM In</th>
                  <th class="th-cell text-center font-mono">AM Out</th>
                  <th class="th-cell text-center font-mono">PM In</th>
                  <th class="th-cell text-center font-mono">PM Out</th>
                  <th class="th-cell text-center font-mono">Hours</th>
                  <th class="th-cell text-center">Late (min)</th>
                  <th class="th-cell text-center">OT (min)</th>
                  <th class="th-cell text-center">UT (min)</th>
                  <th class="th-cell text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(rec, i) in dailyRecords"
                  :key="rec.id"
                  class="border-b border-[#E2E8F0] last:border-0 text-xs row-enter"
                  :class="[`row-status--${rec.status}`, {
                    'bg-slate-50 text-[#6B7A94]':   rec.status === 'weekend',
                    'bg-amber-50':                    rec.status === 'holiday',
                    'bg-indigo-50':                   rec.status === 'on_leave',
                    'bg-white hover:bg-[#FAFBFF]':    !['weekend','holiday','on_leave'].includes(rec.status),
                  }]"
                  :style="{ animationDelay: i * 18 + 'ms' }"
                >
                  <td class="td-cell sticky left-0 z-10" :class="{
                    'bg-slate-50':  rec.status === 'weekend',
                    'bg-amber-50':  rec.status === 'holiday',
                    'bg-indigo-50': rec.status === 'on_leave',
                    'bg-white':     !['weekend','holiday','on_leave'].includes(rec.status),
                  }">
                    <div class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-white text-[10px] font-semibold"
                           :style="avatarStyle(rec.name)">
                        {{ initials(rec.name) }}
                      </div>
                      <div>
                        <p class="font-medium text-[#0F172A] leading-tight text-xs">{{ rec.name }}</p>
                        <p class="text-[10px] text-[#6B7A94] font-mono">{{ rec.employee_id }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="td-cell">
                    <span class="font-medium text-[#0F172A]">{{ formatDate(rec.work_date) }}</span>
                    <span class="text-[#6B7A94] ml-1">{{ rec.day_name }}</span>
                  </td>
                  <td class="td-cell text-center font-mono">{{ rec.morning_in ?? '—' }}</td>
                  <td class="td-cell text-center font-mono">{{ rec.morning_out ?? '—' }}</td>
                  <td class="td-cell text-center font-mono">{{ rec.afternoon_in ?? '—' }}</td>
                  <td class="td-cell text-center font-mono">{{ rec.afternoon_out ?? '—' }}</td>
                  <td class="td-cell text-center font-mono font-medium">
                    {{ rec.hours_rendered > 0 ? Number(rec.hours_rendered).toFixed(2) : '—' }}
                  </td>
                  <td class="td-cell text-center" :class="rec.tardiness_minutes > 0 ? 'text-amber-600 font-medium' : 'text-[#6B7A94]'">
                    {{ rec.tardiness_minutes > 0 ? rec.tardiness_minutes : '—' }}
                  </td>
                  <td class="td-cell text-center" :class="rec.overtime_minutes > 0 ? 'text-emerald-600 font-medium' : 'text-[#6B7A94]'">
                    {{ rec.overtime_minutes > 0 ? rec.overtime_minutes : '—' }}
                  </td>
                  <td class="td-cell text-center" :class="rec.undertime_minutes > 0 ? 'text-red-500 font-medium' : 'text-[#6B7A94]'">
                    {{ rec.undertime_minutes > 0 ? rec.undertime_minutes : '—' }}
                  </td>
                  <td class="td-cell text-center">
                    <StatusBadge v-if="rec.status !== 'weekend'" :status="rec.status" :holiday-name="rec.holiday_name" />
                    <span v-else class="text-[#6B7A94] text-xs">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ADD MANUAL PUNCH MODAL ── -->
    <Teleport to="body">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAddModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:#EEF2FF;">
              <PlusCircleIcon class="w-5 h-5 text-[#2E4A8C]" />
            </div>
            <div>
              <h3 class="font-semibold text-[#0F172A]">Add Manual Punch</h3>
              <p class="text-xs text-[#64748B]">Recorded as manual — will recompute daily DTR</p>
            </div>
            <button @click="showAddModal = false" class="ml-auto text-[#6B7A94] hover:text-[#0F172A]">
              <XIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="submitPunch" class="px-6 py-5 space-y-4">
            <div>
              <label class="form-label">Employee <span class="text-red-400">*</span></label>
              <select v-model="punchForm.employee_id" class="form-input" required>
                <option value="">Select employee…</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} ({{ e.employee_id }})</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="form-label">Date &amp; Time <span class="text-red-400">*</span></label>
                <input v-model="punchForm.punch_time" type="datetime-local" class="form-input" required />
              </div>
              <div>
                <label class="form-label">Type <span class="text-red-400">*</span></label>
                <select v-model="punchForm.punch_type" class="form-input" required>
                  <option value="IN">IN</option>
                  <option value="OUT">OUT</option>
                </select>
              </div>
            </div>
            <div>
              <label class="form-label">Remarks</label>
              <input v-model="punchForm.remarks" type="text" class="form-input" placeholder="Reason for manual entry…" />
            </div>
            <div v-if="punchError" class="text-sm text-[#EF4444] bg-red-50 border border-red-100 rounded-lg px-3 py-2">
              {{ punchError }}
            </div>
            <div class="flex gap-3 pt-1">
              <button type="button" @click="showAddModal = false" class="btn-outline flex-1">Cancel</button>
              <button type="submit" :disabled="punchSaving" class="btn-primary flex-1">
                <Loader2Icon v-if="punchSaving" class="w-4 h-4 animate-spin" />
                <PlusIcon v-else class="w-4 h-4" />
                Save Punch
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── DELETE CONFIRM MODAL ── -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="deleteTarget = null"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <div class="flex flex-col items-center text-center gap-3">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center">
              <Trash2Icon class="w-6 h-6 text-red-500" />
            </div>
            <h3 class="font-semibold text-[#0F172A]">Delete Punch Record?</h3>
            <p class="text-sm text-[#64748B]">
              This will remove the <strong>{{ deleteTarget?.punch_type }}</strong> punch at
              <strong>{{ deleteTarget?.punch_time }}</strong> for <strong>{{ deleteTarget?.name }}</strong>.
              The daily DTR will be recomputed automatically.
            </p>
          </div>
          <div v-if="deleteError" class="mt-3 text-sm text-[#EF4444] bg-red-50 border border-red-100 rounded-lg px-3 py-2 text-center">
            {{ deleteError }}
          </div>
          <div class="flex gap-3 mt-5">
            <button @click="deleteTarget = null" class="btn-outline flex-1">Cancel</button>
            <button @click="doDelete" :disabled="deleting" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 disabled:opacity-60 transition-colors">
              <Loader2Icon v-if="deleting" class="w-4 h-4 animate-spin" />
              <Trash2Icon v-else class="w-4 h-4" />
              Delete
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import StatusBadge    from '@/components/StatusBadge.vue';
import PageHeader     from '@/components/PageHeader.vue';
import EmptyState     from '@/components/EmptyState.vue';
import { useAuthStore }   from '@/stores/auth';
import { useToast }       from '@/composables/useToast';
import { useAvatarColor } from '@/composables/useAvatarColor';

const { toast }      = useToast();
const { avatarStyle } = useAvatarColor();
import {
  Plus as PlusIcon,
  PlusCircle as PlusCircleIcon,
  X as XIcon,
  Loader2 as Loader2Icon,
  RefreshCw as RefreshCwIcon,
  Trash2 as Trash2Icon,
  Clipboard as ClipboardIcon,
  CheckCircle as CheckCircleIcon,
  Clock as ClockIcon,
  Clock as ClockIcon2,
  XCircle as XCircleIcon,
  CalendarDays as CalendarDaysIcon,
  Fingerprint as FingerprintIcon,
  PenLine as PenLineIcon,
  Download as DownloadIcon,
  FileSpreadsheet as FileSpreadsheetIcon,
  Table as TableIcon,
  Users as UsersIcon,
  Calendar as CalendarIcon,
  FileDown as FileDownIcon,
} from 'lucide-vue-next';

const auth = useAuthStore();

// ── Tabs ──────────────────────────────────────────
const tabs = [
  { key: 'punches', label: 'Punch Logs'     },
  { key: 'daily',   label: 'Daily Records'  },
];
const activeTab = ref('punches');

// ── Shared reference data ──────────────────────────
const employees  = ref([]);
const departments = ref([]);

const now = new Date();
const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' },
  { value: 3, label: 'March' },   { value: 4, label: 'April' },
  { value: 5, label: 'May' },     { value: 6, label: 'June' },
  { value: 7, label: 'July' },    { value: 8, label: 'August' },
  { value: 9, label: 'September'},{ value: 10, label: 'October' },
  { value: 11, label: 'November'},{ value: 12, label: 'December' },
];
const years = Array.from({ length: 6 }, (_, i) => now.getFullYear() - i);

// ── Punch Logs ─────────────────────────────────────
const punchLogs    = ref([]);
const punchLoading = ref(false);

const punchFilters = reactive({
  date:        now.toISOString().split('T')[0],
  employee_id: '',
  source:      '',
  punch_type:  '',
});

const filteredPunchLogs = computed(() => punchLogs.value);

const punchStats = computed(() => {
  const total = punchLogs.value.length;
  const ins   = punchLogs.value.filter(l => l.punch_type === 'IN').length;
  const outs  = punchLogs.value.filter(l => l.punch_type === 'OUT').length;
  const manual = punchLogs.value.filter(l => l.source === 'manual').length;
  return [
    { label: 'Total Punches', value: total,  icon: ClipboardIcon,   color: '#2E4A8C' },
    { label: 'IN',            value: ins,    icon: CheckCircleIcon, color: '#059669' },
    { label: 'OUT',           value: outs,   icon: XCircleIcon,     color: '#DC2626' },
    { label: 'Manual',        value: manual, icon: PenLineIcon,     color: '#D97706' },
  ];
});

function statVars(hex) {
  return { '--stat-color': hex, '--stat-bg': hex + '14', '--stat-glow': hex + '30' };
}

async function loadPunches() {
  punchLoading.value = true;
  try {
    const params = { date: punchFilters.date };
    if (punchFilters.employee_id) params.employee_id = punchFilters.employee_id;
    if (punchFilters.source)      params.source      = punchFilters.source;
    if (punchFilters.punch_type)  params.punch_type  = punchFilters.punch_type;
    const res = await axios.get('/api/attendance/logs', { params });
    punchLogs.value = res.data;
  } finally {
    punchLoading.value = false;
  }
}

const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

watch(() => punchFilters.date, debounce(loadPunches));

// ── Daily Records ──────────────────────────────────
const dailyRecords = ref([]);
const dailyLoading = ref(false);

const dailyFilters = reactive({
  month:         now.getMonth() + 1,
  year:          now.getFullYear(),
  department_id: '',
  employee_id:   '',
});

const dailyStats = computed(() => {
  const recs = dailyRecords.value.filter(r => !['weekend'].includes(r.status));
  const present  = recs.filter(r => ['present', 'late'].includes(r.status)).length;
  const late     = recs.filter(r => r.status === 'late').length;
  const absent   = recs.filter(r => r.status === 'absent').length;
  const onLeave  = recs.filter(r => r.status === 'on_leave').length;
  return [
    { label: 'Days Present', value: present, icon: CheckCircleIcon,  color: '#059669' },
    { label: 'Late',         value: late,    icon: ClockIcon2,       color: '#D97706' },
    { label: 'Absent',       value: absent,  icon: XCircleIcon,      color: '#DC2626' },
    { label: 'On Leave',     value: onLeave, icon: CalendarDaysIcon, color: '#4F46E5' },
  ];
});

async function loadDaily() {
  dailyLoading.value = true;
  try {
    const params = { month: dailyFilters.month, year: dailyFilters.year };
    if (dailyFilters.department_id) params.department_id = dailyFilters.department_id;
    if (dailyFilters.employee_id)   params.employee_id   = dailyFilters.employee_id;
    const res = await axios.get('/api/attendance/daily', { params });
    dailyRecords.value = res.data;
  } finally {
    dailyLoading.value = false;
  }
}

watch([() => dailyFilters.month, () => dailyFilters.year, () => dailyFilters.department_id, () => dailyFilters.employee_id], debounce(loadDaily));

// ── Add Manual Punch ───────────────────────────────
const showAddModal = ref(false);
const punchSaving  = ref(false);
const punchError   = ref('');
const punchForm    = reactive({
  employee_id: '',
  punch_time:  '',
  punch_type:  'IN',
  remarks:     '',
});

function openAddPunch() {
  punchForm.employee_id = '';
  punchForm.punch_time  = new Date().toISOString().slice(0, 16);
  punchForm.punch_type  = 'IN';
  punchForm.remarks     = '';
  punchError.value      = '';
  showAddModal.value    = true;
}

async function submitPunch() {
  punchError.value  = '';
  punchSaving.value = true;
  try {
    await axios.post('/api/attendance/logs', punchForm);
    showAddModal.value = false;
    toast('Manual punch recorded successfully.', 'success');
    await loadPunches();
  } catch (e) {
    punchError.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Failed to save punch.';
  } finally {
    punchSaving.value = false;
  }
}

// ── Delete Punch ───────────────────────────────────
const deleteTarget = ref(null);
const deleting     = ref(false);
const deleteError  = ref('');

function confirmDelete(log) {
  deleteTarget.value = log;
  deleteError.value  = '';
}

async function doDelete() {
  deleting.value = true;
  deleteError.value = '';
  try {
    await axios.delete(`/api/attendance/logs/${deleteTarget.value.id}`);
    deleteTarget.value = null;
    toast('Punch record deleted.', 'warning');
    await loadPunches();
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Failed to delete.';
  } finally {
    deleting.value = false;
  }
}

// ── Bulk Export ────────────────────────────────────
const showExportModal = ref(false);
const exportTarget    = ref('daily');

function openExport(type) {
  exportTarget.value    = type;
  showExportModal.value = true;
}

const exportMeta = computed(() => {
  if (exportTarget.value === 'daily') {
    const month = months.find(m => m.value === dailyFilters.month)?.label ?? '';
    const dept  = dailyFilters.department_id
      ? (departments.value.find(d => d.id == dailyFilters.department_id)?.name ?? 'Selected Dept')
      : 'All Departments';
    const emp   = dailyFilters.employee_id
      ? (employees.value.find(e => e.id == dailyFilters.employee_id)?.name ?? 'Selected Employee')
      : 'All Employees';
    return {
      title:    'Daily Attendance Records',
      period:   `${month} ${dailyFilters.year}`,
      scope:    `${dept} · ${emp}`,
      count:    dailyRecords.value.length,
      filename: `Attendance_Daily_${month}_${dailyFilters.year}.csv`,
      columns:  ['Employee ID','Name','Department','Date','Day','AM In','AM Out','PM In','PM Out','Hours','Late (min)','OT (min)','UT (min)','Status'],
      preview:  dailyRecords.value.slice(0, 3).map(r => [r.employee_id, r.name, r.work_date, r.status]),
    };
  } else {
    const dept = punchFilters.employee_id
      ? (employees.value.find(e => e.id == punchFilters.employee_id)?.name ?? 'Selected Employee')
      : 'All Employees';
    return {
      title:    'Punch Log Records',
      period:   punchFilters.date,
      scope:    dept,
      count:    filteredPunchLogs.value.length,
      filename: `Attendance_Punches_${punchFilters.date}.csv`,
      columns:  ['Employee ID','Name','Department','Time','Type','Source','Device','Remarks'],
      preview:  filteredPunchLogs.value.slice(0, 3).map(r => [r.employee_id, r.name, r.punch_time, r.punch_type]),
    };
  }
});

function doExport() {
  exportTarget.value === 'daily' ? exportDailyCSV() : exportPunchCSV();
  showExportModal.value = false;
}

function exportDailyCSV() {
  const rows = dailyRecords.value;
  if (!rows.length) return;
  const headers = ['Employee ID','Name','Department','Date','Day','AM In','AM Out','PM In','PM Out','Hours','Late (min)','OT (min)','UT (min)','Status'];
  const lines = rows.map(r => [
    r.employee_id, r.name, r.department ?? '', r.work_date, r.day_name,
    r.morning_in ?? '', r.morning_out ?? '', r.afternoon_in ?? '', r.afternoon_out ?? '',
    r.hours_rendered > 0 ? Number(r.hours_rendered).toFixed(2) : '',
    r.tardiness_minutes > 0 ? r.tardiness_minutes : '',
    r.overtime_minutes  > 0 ? r.overtime_minutes  : '',
    r.undertime_minutes > 0 ? r.undertime_minutes : '',
    r.status,
  ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','));
  triggerDownload('﻿' + [headers.join(','), ...lines].join('\n'), exportMeta.value.filename);
}

function exportPunchCSV() {
  const rows = filteredPunchLogs.value;
  if (!rows.length) return;
  const headers = ['Employee ID','Name','Department','Date','Time','Type','Source','Device','Remarks'];
  const lines = rows.map(r => [
    r.employee_id, r.name, r.department ?? '', punchFilters.date,
    r.punch_time, r.punch_type, r.source, r.device_id ?? '', r.remarks ?? '',
  ].map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','));
  triggerDownload('﻿' + [headers.join(','), ...lines].join('\n'), exportMeta.value.filename);
}

function triggerDownload(content, filename) {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}

// ── Helpers ────────────────────────────────────────
function initials(name) {
  return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() ?? '?';
}

function formatDate(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function formatLogDate(log) {
  if (!log.punch_date) return '';
  const d = new Date(log.punch_date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
  return log.punch_day ? `${d} · ${log.punch_day}` : d;
}

// ── Init ───────────────────────────────────────────
onMounted(async () => {
  const [empRes, deptRes] = await Promise.all([
    axios.get('/api/employees'),
    axios.get('/api/departments'),
  ]);
  employees.value   = empRes.data;
  departments.value = deptRes.data;
  await Promise.all([loadPunches(), loadDaily()]);
});
</script>

<style scoped>
@reference "../css/app.css";
.th-cell     { @apply px-4 py-3 text-xs font-semibold text-[#64748B] uppercase tracking-wide bg-white; }
.td-cell     { @apply px-4 py-3; }
.filter-input { @apply text-sm border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#2E4A8C]; }
.form-label  { @apply block text-sm font-medium text-[#0F172A] mb-1.5; }
.form-input  { @apply w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#2E4A8C]; }
.btn-primary { @apply inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#2E4A8C] text-white text-sm font-medium rounded-lg hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors; }
.btn-outline  { @apply inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F0F4FF] transition-colors; }
.refresh-btn  { @apply inline-flex items-center gap-2 px-4 py-1.5 bg-[#2E4A8C] text-white text-sm font-medium rounded-lg hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors; }
.badge-in    { @apply inline-flex px-2 py-0.5 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700; }
.badge-out   { @apply inline-flex px-2 py-0.5 text-xs font-semibold rounded-md bg-rose-50 text-rose-700; }
.badge-bio   { @apply inline-flex px-2 py-0.5 text-xs font-medium rounded-md bg-slate-100 text-slate-600; }
.badge-manual { @apply inline-flex px-2 py-0.5 text-xs font-medium rounded-md bg-amber-50 text-amber-700; }

/* ── Skeleton shimmer ────────────────────────────────────────── */
.skel-row {
  display: flex; align-items: center; gap: 16px;
  padding: 11px 16px; border-bottom: 1px solid #F9FAFB;
}
.skel {
  background: linear-gradient(90deg, #F1F5F9 25%, #E8EDF5 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: att-shimmer 1.4s infinite linear;
  border-radius: 4px;
  flex-shrink: 0;
}
.skel-lines { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.skel-text  { height: 12px; }
@keyframes att-shimmer { to { background-position: -200% 0; } }

/* ── Table minimum widths so columns never squish ────────────── */
.att-table { min-width: 620px; }
.daily-table { min-width: 860px; }

/* ── Mobile ──────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .filter-input { font-size: 13px; }
}
@media (max-width: 640px) {
  /* Tabs */
  .att-tabs      { padding-left: 12px; padding-right: 12px; gap: 16px; }

  /* Outer padding */
  .att-body      { padding: 12px !important; }

  /* Filter bar: stack on mobile */
  .att-filters   { flex-direction: column; align-items: stretch; gap: 8px; padding: 12px; }
  .att-filters > div { width: 100%; }
  .filter-input  { width: 100%; }
  .filter-input.w-44 { width: 100% !important; }

  /* Table wrapper */
  .att-table-wrap { overflow-x: auto; }
  .att-table      { min-width: 620px; }

  /* Daily records filters */
  .rec-filters   { flex-direction: column; align-items: stretch; gap: 8px; padding: 12px; }
  .rec-filters > div { width: 100%; }

  /* Pagination */
  .pagination-wrap { flex-direction: column; align-items: center; gap: 8px; padding: 10px 12px; }

  /* Action buttons in header */
  .att-actions   { width: 100%; }
  .refresh-btn   { width: 100%; justify-content: center; }
}
</style>
