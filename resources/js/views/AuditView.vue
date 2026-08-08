<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Audit Trail" subtitle="Tamper-proof log of all administrative actions in the system" :icon="ShieldCheckIcon" />

    <!-- Filters -->
    <div class="px-6 py-3 border-b border-[#E2E8F0] bg-white flex flex-wrap items-center gap-3">
      <input
        v-model="filters.date"
        type="date"
        class="audit-filter-input"
      />
      <select v-model="filters.action" class="audit-filter-input">
        <option value="">All Actions</option>
        <option value="CREATE">Create</option>
        <option value="UPDATE">Update</option>
        <option value="DELETE">Delete</option>
        <option value="DEACTIVATE">Deactivate</option>
        <option value="APPROVE">Approve</option>
        <option value="DENY">Deny</option>
      </select>
      <select v-model="filters.table" class="audit-filter-input">
        <option value="">All Tables</option>
        <option value="employees">Employees</option>
        <option value="attendance_logs">Attendance Logs</option>
        <option value="leave_requests">Leave Requests</option>
        <option value="holidays">Holidays</option>
        <option value="shifts">Shifts</option>
        <option value="departments">Departments</option>
      </select>
      <button
        @click="load(1)"
        class="ml-auto px-4 py-1.5 text-sm font-semibold text-white rounded-lg transition-colors"
        style="background: #0E1EC0;"
        onmouseover="this.style.background='#1228CC'" onmouseout="this.style.background='#0E1EC0'"
      >
        Apply Filter
      </button>
      <button @click="resetFilters" class="px-4 py-1.5 text-sm font-medium border border-[#E4E9F2] rounded-lg text-[#64748B] hover:bg-[#F4F6FB] transition-colors bg-white">
        Reset
      </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto bg-[#F4F6FB]">

      <!-- Loading skeleton -->
      <div v-if="loading" class="p-6 space-y-2">
        <div v-for="i in 8" :key="i" class="bg-white rounded-lg border border-[#E2E8F0] px-4 py-3 animate-pulse flex gap-4">
          <div class="h-3 bg-slate-200 rounded w-32"></div>
          <div class="h-3 bg-slate-100 rounded w-24"></div>
          <div class="h-3 bg-slate-100 rounded w-16 ml-auto"></div>
        </div>
      </div>

      <!-- Table -->
      <div v-else class="p-6">
        <div class="bg-white rounded-xl border border-[#E4E9F2] overflow-hidden shadow-[0_2px_12px_rgba(8,14,128,0.07),0_1px_3px_rgba(8,14,128,0.04)]">
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-[#EEF2FA] bg-[#F7F8FD]">
              <tr>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Timestamp</th>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">User</th>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Action</th>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Table</th>
                <th class="text-center px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Record</th>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Reason</th>
                <th class="text-left px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="logs.length === 0">
                <td colspan="7" class="px-4 py-12 text-center text-[#94A3B8]">No audit logs match the current filters.</td>
              </tr>
              <tr
                v-for="(log, i) in logs"
                :key="log.id"
                class="border-b border-[#F1F5F9] last:border-0"
                :class="i % 2 === 1 ? 'bg-[#FAFBFF]' : ''"
              >
                <td class="px-4 py-3 font-mono text-xs text-[#64748B] whitespace-nowrap">{{ log.created_at }}</td>
                <td class="px-4 py-3">
                  <p class="text-sm font-medium text-[#0F172A]">{{ log.user }}</p>
                  <p class="text-xs text-[#94A3B8] font-mono">{{ log.employee_id }}</p>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full"
                    :class="actionClass(log.action)"
                  >
                    {{ log.action }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs font-mono text-[#64748B]">{{ log.table }}</td>
                <td class="px-4 py-3 text-center text-xs font-mono text-[#94A3B8]">#{{ log.record_id }}</td>
                <td class="px-4 py-3 text-xs text-[#64748B] max-w-[200px] truncate" :title="log.reason">{{ log.reason ?? '—' }}</td>
                <td class="px-4 py-3 text-xs font-mono text-[#94A3B8]">{{ log.ip }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between mt-4">
          <p class="text-sm text-[#64748B]">
            Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }} entries
          </p>
          <div class="flex items-center gap-1">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="load(page)"
              class="w-8 h-8 flex items-center justify-center rounded-md text-sm transition-colors"
              :class="page === meta.current_page
                ? 'text-white font-semibold'
                : 'text-[#64748B] hover:bg-[#F0F4FF]'"
              :style="page === meta.current_page ? 'background:#0E1EC0' : ''"
            >
              {{ page }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import { ShieldCheck as ShieldCheckIcon } from 'lucide-vue-next';

const logs    = ref([]);
const meta    = ref(null);
const loading = ref(false);

const filters = reactive({ date: '', action: '', table: '' });

const actionClass = (action) => {
  const map = {
    CREATE:     'bg-emerald-100 text-emerald-700',
    UPDATE:     'bg-amber-100 text-amber-700',
    DELETE:     'bg-red-100 text-red-700',
    DEACTIVATE: 'bg-slate-100 text-slate-600',
    APPROVE:    'bg-blue-100 text-blue-700',
    DENY:       'bg-orange-100 text-orange-700',
  };
  return map[action] ?? 'bg-gray-100 text-gray-600';
};

const visiblePages = computed(() => {
  if (!meta.value) return [];
  const { current_page, last_page } = meta.value;
  const pages = [];
  const start = Math.max(1, current_page - 2);
  const end   = Math.min(last_page, current_page + 2);
  for (let p = start; p <= end; p++) pages.push(p);
  return pages;
});

async function load(page = 1) {
  loading.value = true;
  try {
    const res = await axios.get('/api/audit-logs', {
      params: { page, ...filters },
    });
    logs.value = res.data.data;
    meta.value = res.data.meta;
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.date   = '';
  filters.action = '';
  filters.table  = '';
  load(1);
}

onMounted(() => load(1));
</script>

<style scoped>

/* ── Filter bar ─────────────────────────────────────── */
.audit-filter-input {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 6px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.audit-filter-input:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

/* ── Table th/td ────────────────────────────────────── */
thead th {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em; color: #5A6899;
}
tbody tr { border-bottom: 1px solid #F1F5F9; transition: background 0.12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:nth-child(even) { background: #FAFBFF; }
tbody tr:hover { background: #F0F4FF !important; }
tbody td { padding: 11px 16px; }

/* ── Mobile ─────────────────────────────────────────── */
@media (max-width: 768px) {
  .px-6.py-3.border-b { flex-wrap: wrap; gap: 8px; }
}
@media (max-width: 640px) {
  thead th, tbody td { padding: 9px 12px; }
}
</style>
