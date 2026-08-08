<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Admin Accounts" subtitle="All privileged accounts across every campus" :icon="ShieldCheckIcon" />

    <!-- Filters -->
    <div class="px-6 py-3 border-b border-[#E2E8F0] bg-white flex flex-wrap items-center gap-3">
      <div class="relative">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#94A3B8]" />
        <input v-model="search" placeholder="Search name or ID…"
          class="pl-9 pr-3 py-1.5 text-sm border border-[#E2E8F0] rounded-md w-52 focus:outline-none focus:ring-2 focus:ring-[#0E1EC0]" />
      </div>
      <select v-model="filterCampus" class="select-sm">
        <option value="">All Campuses</option>
        <option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filterRole" class="select-sm">
        <option value="">All Roles</option>
        <option value="super_admin">Super Admin</option>
        <option value="admin">Admin</option>
        <option value="hr_officer">HR Officer</option>
        <option value="department_head">Department Head</option>
      </select>
      <span class="ml-auto text-xs text-[#94A3B8]">{{ filtered.length }} account{{ filtered.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Table -->
    <div class="flex-1 overflow-auto bg-[#F4F6FB] p-6">
      <div class="bg-white border border-[#E4E9F2] rounded-xl overflow-hidden shadow-[0_2px_12px_rgba(8,14,128,0.07),0_1px_3px_rgba(8,14,128,0.04)]">

        <div v-if="loading" class="flex items-center justify-center h-40 text-[#94A3B8] text-sm gap-2">
          <Loader2Icon class="w-4 h-4 animate-spin" /> Loading…
        </div>

        <div v-else-if="!filtered.length" class="flex flex-col items-center justify-center h-40 text-[#94A3B8]">
          <ShieldCheckIcon class="w-8 h-8 mb-2 opacity-30" />
          <p class="text-sm">No accounts found.</p>
        </div>

        <table v-else class="w-full text-sm">
          <thead class="border-b border-[#EEF2FA] bg-[#F7F8FD]">
            <tr>
              <th class="th-cell text-left">#</th>
              <th class="th-cell text-left">Account</th>
              <th class="th-cell text-left">Campus</th>
              <th class="th-cell text-left">Department</th>
              <th class="th-cell text-center">Role</th>
              <th class="th-cell text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(acc, i) in filtered" :key="acc.id"
                class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#F8FAFC] transition-colors"
                :class="{ 'opacity-50': !acc.is_active }">
              <td class="td-cell text-center text-xs text-[#94A3B8] font-mono">{{ i + 1 }}</td>
              <td class="td-cell">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                       style="background: linear-gradient(135deg, #0E1EC0, #1228CC);">
                    {{ initials(acc.name) }}
                  </div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ acc.name }}</p>
                    <p class="text-xs text-[#94A3B8] font-mono">{{ acc.employee_id }}</p>
                  </div>
                </div>
              </td>
              <td class="td-cell text-[#64748B] text-xs">{{ acc.campus ?? '—' }}</td>
              <td class="td-cell text-[#64748B] text-xs">{{ acc.department ?? '—' }}</td>
              <td class="td-cell text-center">
                <RoleBadge :role="acc.role" />
              </td>
              <td class="td-cell text-center">
                <span :class="acc.is_active ? 'badge-green' : 'badge-gray'">
                  {{ acc.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import {
  ShieldCheck as ShieldCheckIcon,
  Search as SearchIcon,
  Loader2 as Loader2Icon,
} from 'lucide-vue-next';

const accounts     = ref([]);
const campuses     = ref([]);
const loading      = ref(false);
const search       = ref('');
const filterCampus = ref('');
const filterRole   = ref('');

const PRIVILEGED = ['super_admin', 'admin', 'hr_officer', 'department_head'];

const filtered = computed(() => {
  let list = accounts.value;
  const s = search.value.toLowerCase();
  if (s) list = list.filter(a =>
    a.name.toLowerCase().includes(s) || a.employee_id.toLowerCase().includes(s)
  );
  if (filterCampus.value) list = list.filter(a => a.campus_id == filterCampus.value);
  if (filterRole.value)   list = list.filter(a => a.role === filterRole.value);
  return list;
});

function initials(name) {
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

async function load() {
  loading.value = true;
  try {
    const [empRes, campRes] = await Promise.all([
      axios.get('/api/employees'),
      axios.get('/api/campuses'),
    ]);
    accounts.value = empRes.data.filter(e => PRIVILEGED.includes(e.role));
    campuses.value = campRes.data;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
@reference "../css/app.css";

/* ── Filter bar ───────────────────────────────────── */
.select-sm {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 6px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.select-sm:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

/* ── Table ────────────────────────────────────────── */
.th-cell {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #5A6899; white-space: nowrap;
}
.td-cell { padding: 12px 16px; }

tbody tr { border-bottom: 1px solid #F1F5F9; transition: background 0.12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8FAFF; }

/* ── Badges ───────────────────────────────────────── */
.badge-green { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #ECFDF5; color: #059669; }
.badge-gray  { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #F1F5F9; color: #64748B; }

/* ── Avatar ───────────────────────────────────────── */
.admin-avatar {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  background: linear-gradient(135deg, #1228CC 0%, #080E80 100%);
  display: flex; align-items: center; justify-content: center;
}

/* ── Mobile ───────────────────────────────────────── */
@media (max-width: 768px) {
  .px-6.py-3 { flex-wrap: wrap; padding: 12px; }
  .select-sm { width: 100%; }
}
@media (max-width: 640px) {
  .th-cell { padding: 9px 12px; }
  .td-cell { padding: 10px 12px; }
}
</style>
