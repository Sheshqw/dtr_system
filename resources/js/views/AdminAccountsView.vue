<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Admin Accounts" subtitle="All privileged accounts across every campus" :icon="ShieldCheckIcon" />

    <!-- Filters -->
    <div class="px-6 py-3 border-b border-[#E2E8F0] bg-white flex flex-wrap items-center gap-3">
      <div class="relative">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#6B7A94]" />
        <input v-model="filters.search" placeholder="Search name or ID…"
          class="pl-9 pr-3 py-1.5 text-sm border border-[#E2E8F0] rounded-md w-52 focus:outline-none focus:ring-2 focus:ring-[#2E4A8C]" />
      </div>
      <select v-model="filters.campus" class="select-sm">
        <option value="">All Campuses</option>
        <option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.role" class="select-sm">
        <option value="">All Roles</option>
        <option value="super_admin">Super Admin</option>
        <option value="admin">Admin</option>
        <option value="hr_officer">HR Officer</option>
        <option value="department_head">Department Head</option>
      </select>
      <span class="ml-auto text-xs text-[#6B7A94]">{{ filtered.length }} account{{ filtered.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Table -->
    <div class="flex-1 overflow-auto bg-[#F4F6FB] p-6">
      <div class="bg-white border border-[#E4E9F2] rounded-xl overflow-hidden shadow-[0_2px_12px_rgba(27, 42, 94,0.07),0_1px_3px_rgba(27, 42, 94,0.04)]">

        <div v-if="loading" class="flex items-center justify-center h-40 text-[#6B7A94] text-sm gap-2">
          <Loader2Icon class="w-4 h-4 animate-spin" /> Loading…
        </div>

        <EmptyState v-else-if="!filtered.length" title="No accounts found" description="No privileged accounts match your current filters." />

        <table v-else class="w-full text-sm">
          <thead class="border-b border-[#EEF2FA] bg-[#F7F8FD]">
            <tr>
              <th class="th-cell text-left">#</th>
              <th class="th-cell text-left th-sort" @click="sort('name')">
                <span class="inline-flex items-center gap-1">Account <SortIcon :state="sortState('name')" /></span>
              </th>
              <th class="th-cell text-left th-sort" @click="sort('campus')">
                <span class="inline-flex items-center gap-1">Campus <SortIcon :state="sortState('campus')" /></span>
              </th>
              <th class="th-cell text-left th-sort" @click="sort('department')">
                <span class="inline-flex items-center gap-1">Department <SortIcon :state="sortState('department')" /></span>
              </th>
              <th class="th-cell text-center th-sort" @click="sort('role')">
                <span class="inline-flex items-center justify-center gap-1">Role <SortIcon :state="sortState('role')" /></span>
              </th>
              <th class="th-cell text-center th-sort" @click="sort('status')">
                <span class="inline-flex items-center justify-center gap-1">Status <SortIcon :state="sortState('status')" /></span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(acc, i) in sorted" :key="acc.id"
                class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#F8FAFC] transition-colors"
                :class="{ 'opacity-50': !acc.is_active }">
              <td class="td-cell text-center text-xs text-[#6B7A94] font-mono">{{ i + 1 }}</td>
              <td class="td-cell">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-semibold"
                       :style="avatarStyle(acc.name)">
                    {{ initials(acc.name) }}
                  </div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ acc.name }}</p>
                    <p class="text-xs text-[#6B7A94] font-mono">{{ acc.employee_id }}</p>
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
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import { useAvatarColor } from '@/composables/useAvatarColor';
import RoleBadge from '@/components/RoleBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import SortIcon from '@/components/SortIcon.vue';
import { useToast } from '@/composables/useToast';
import { useSortable } from '@/composables/useSortable';
import { usePersistedFilters } from '@/composables/usePersistedFilters';
import {
  ShieldCheck as ShieldCheckIcon,
  Search as SearchIcon,
  Loader2 as Loader2Icon,
} from 'lucide-vue-next';

const { toast } = useToast();
const { avatarStyle } = useAvatarColor();

const accounts = ref([]);
const campuses = ref([]);
const loading  = ref(false);

const filters = reactive({ search: '', campus: '', role: '' });
usePersistedFilters('admin-accounts-filters', filters, ['search']);

const PRIVILEGED = ['super_admin', 'admin', 'hr_officer', 'department_head'];

const filtered = computed(() => {
  let list = accounts.value;
  const s = filters.search.toLowerCase();
  if (s) list = list.filter(a =>
    a.name.toLowerCase().includes(s) || a.employee_id.toLowerCase().includes(s)
  );
  if (filters.campus) list = list.filter(a => a.campus_id == filters.campus);
  if (filters.role)   list = list.filter(a => a.role === filters.role);
  return list;
});

const { sortKey, sortDir, sort, sorted } = useSortable(filtered, {
  name:       (a) => a.name?.toLowerCase() ?? '',
  campus:     (a) => a.campus ?? '',
  department: (a) => a.department ?? '',
  role:       (a) => a.role ?? '',
  status:     (a) => (a.is_active ? 0 : 1),
});
function sortState(key) {
  return sortKey.value === key ? sortDir.value : null;
}

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
  } catch (e) {
    toast(e.response?.data?.message ?? 'Could not load admin accounts. Please refresh the page.', 'error');
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
.select-sm:focus { outline: none; border-color: #2E4A8C; box-shadow: 0 0 0 3px rgba(46, 74, 140,0.08); }

/* ── Table ────────────────────────────────────────── */
.th-cell {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #64748B; white-space: nowrap;
}
.td-cell { padding: 12px 16px; }

/* ── Sortable column headers ────────────────────────────── */
.th-sort { cursor: pointer; user-select: none; transition: color 0.12s; }
.th-sort:hover { color: #2E4A8C; }

tbody tr { border-bottom: 1px solid #F1F5F9; transition: background 0.12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8FAFF; }

/* ── Badges ───────────────────────────────────────── */
.badge-green { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #ECFDF5; color: #059669; }
.badge-gray  { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #F1F5F9; color: #64748B; }

/* ── Avatar ───────────────────────────────────────── */
.admin-avatar {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  background: linear-gradient(135deg, #3D5CA3 0%, #1B2A5E 100%);
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
