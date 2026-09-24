<template>
  <div class="flex flex-col h-full">

    <PageHeader
      title="Access Control"
      subtitle="Grant or revoke individual features per employee — overrides their role's defaults"
      :icon="LockIcon"
    />

    <div class="flex-1 overflow-hidden flex flex-col lg:flex-row bg-[#F4F6FB]">

      <!-- ── Employee selector ─────────────────────────────────── -->
      <div class="ac-picker">
        <div class="ac-picker-head">
          <p class="ac-picker-title">All Employees</p>
          <span v-if="!loadingEmployees" class="ac-picker-count">{{ employees.length }}</span>
        </div>
        <div class="relative px-3 pb-2">
          <SearchIcon class="absolute left-6 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#6B7A94] pointer-events-none" />
          <input
            v-model="search"
            placeholder="Search employees…"
            class="w-full pl-8 pr-8 py-2 text-sm border border-[#E4E9F2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2E4A8C]"
          />
          <button
            v-if="search"
            @click="search = ''"
            class="absolute right-5 top-1/2 -translate-y-1/2 p-0.5 rounded-full text-[#94A3B8] hover:text-[#475569] hover:bg-[#F1F5F9] transition-colors"
            aria-label="Clear search"
          >
            <XIcon class="w-3.5 h-3.5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-2 pb-2">
          <div v-if="loadingEmployees" class="py-10 text-center">
            <Loader2Icon class="w-4 h-4 animate-spin mx-auto text-[#6B7A94]" />
          </div>

          <EmptyState
            v-else-if="employees.length === 0"
            title="No employees found"
            description="Try a different search term."
          />

          <button
            v-else
            v-for="emp in employees"
            :key="emp.id"
            @click="selectEmployee(emp)"
            class="ac-emp-row"
            :class="{ 'ac-emp-row--active': selected?.id === emp.id }"
          >
            <span class="ac-emp-avatar" :style="avatarStyle(emp.name)">{{ initials(emp.name) }}</span>
            <span class="flex-1 min-w-0 text-left">
              <span class="block text-sm font-semibold text-[#0F172A] truncate">{{ emp.name }}</span>
              <span class="block text-xs text-[#6B7A94] truncate">{{ emp.department ?? emp.position ?? '—' }}</span>
            </span>
            <RoleBadge :role="emp.role" />
          </button>
        </div>
      </div>

      <!-- ── Feature toggles + history ────────────────────────────── -->
      <div class="flex-1 overflow-y-auto p-6 space-y-5">

        <EmptyState
          v-if="!selected"
          title="Select an employee"
          description="Choose someone from the list to view and manage their feature access."
        />

        <template v-else>
          <div class="ac-panel">
            <div class="ac-panel-head">
              <div class="flex items-center gap-3">
                <span class="ac-emp-avatar ac-emp-avatar--lg" :style="avatarStyle(selected.name)">{{ initials(selected.name) }}</span>
                <div>
                  <p class="text-sm font-bold text-[#0F172A]">{{ selected.name }}</p>
                  <p class="text-xs text-[#64748B]">Role default shown below is for <RoleBadge :role="selected.role" /></p>
                </div>
              </div>
            </div>

            <!-- ── Scope selector: this one employee, or everyone at once ── -->
            <div class="ac-scope-row">
              <span class="ac-scope-label">Apply changes to</span>
              <div class="ac-scope-toggle" role="group" aria-label="Permission change scope">
                <button
                  type="button"
                  class="ac-scope-btn"
                  :class="{ 'ac-scope-btn--active': scope === 'one' }"
                  @click="scope = 'one'"
                >
                  <UserIcon class="w-3.5 h-3.5" /> This employee
                </button>
                <button
                  type="button"
                  class="ac-scope-btn"
                  :class="{ 'ac-scope-btn--active': scope === 'all' }"
                  @click="scope = 'all'"
                >
                  <UsersIcon class="w-3.5 h-3.5" /> All employees
                </button>
              </div>
              <span v-if="scope === 'all'" class="ac-scope-warning">
                <TriangleAlertIcon class="w-3.5 h-3.5" />
                Toggling a feature below will change it for every employee in the system, not just {{ selected.name }}.
              </span>
            </div>

            <div v-if="loadingPerm" class="py-10 text-center">
              <Loader2Icon class="w-4 h-4 animate-spin mx-auto text-[#6B7A94]" />
            </div>

            <div v-else class="divide-y divide-[#F1F5F9]">
              <div v-for="(label, key) in catalog.features" :key="key" class="ac-feature-row">
                <span class="ac-feature-icon" :style="{ '--f-color': featureMeta(key).color }">
                  <component :is="featureMeta(key).icon" class="w-4 h-4" />
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-[#0F172A]">{{ label }}</p>
                  <p class="text-xs text-[#6B7A94] mt-0.5">
                    <span v-if="isOverridden(key)" class="ac-override-tag"><span class="ac-override-dot"></span>Custom override</span>
                    <span v-else>Using {{ roleLabel(selected.role) }} default ({{ perm.defaults[key] ? 'On' : 'Off' }})</span>
                  </p>
                </div>

                <button
                  v-if="isOverridden(key) || scope === 'all'"
                  @click="resetFeature(key)"
                  :disabled="savingKey === key"
                  class="ac-reset-btn"
                  :title="scope === 'all' ? 'Reset to role default for every employee' : 'Reset to role default'"
                >Reset</button>

                <button
                  role="switch"
                  :aria-checked="perm.effective[key]"
                  :disabled="savingKey === key"
                  @click="toggleFeature(key)"
                  class="ac-switch"
                  :class="{ 'ac-switch--on': perm.effective[key] }"
                >
                  <span class="ac-switch-knob" />
                </button>
              </div>
            </div>
          </div>

          <!-- ── Recent changes ─────────────────────────────────── -->
          <div class="ac-panel">
            <div class="ac-panel-head">
              <div class="flex items-center gap-2.5">
                <span class="ac-history-icon"><HistoryIcon class="w-4 h-4" /></span>
                <p class="text-sm font-bold text-[#0F172A]">Recent Permission Changes</p>
              </div>
              <RouterLink :to="{ name: 'audit' }" class="text-xs font-semibold text-[#2E4A8C] hover:underline">View audit trail</RouterLink>
            </div>

            <EmptyState
              v-if="!loadingHistory && history.length === 0"
              title="No changes yet"
              description="Permission changes for this system will appear here."
            />

            <div v-else class="divide-y divide-[#F1F5F9]">
              <div v-for="log in history" :key="log.id" class="ac-history-row">
                <p class="text-sm text-slate-700">
                  <span class="font-semibold text-[#0F172A]">{{ log.user }}</span> {{ log.reason }}
                </p>
                <p class="text-xs text-[#6B7A94] mt-0.5">{{ formatTime(log.created_at) }}</p>
              </div>
            </div>
          </div>
        </template>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import EmptyState from '@/components/EmptyState.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { useDebounce } from '@/composables/useDebounce';
import { useAvatarColor } from '@/composables/useAvatarColor';
import {
  Lock as LockIcon,
  Search as SearchIcon,
  Loader2 as Loader2Icon,
  User as UserIcon,
  Users as UsersIcon,
  TriangleAlert as TriangleAlertIcon,
  X as XIcon,
  History as HistoryIcon,
  FileDown as FileDownIcon,
  Timer as TimerIcon,
  Calendar as CalendarIcon,
  HeartPulse as HeartPulseIcon,
  BarChart2 as BarChart2Icon,
  Bell as BellIcon,
} from 'lucide-vue-next';

// Icon + accent color per feature — purely a frontend lookup (the backend
// catalog is just a flat key => label map), so this can't drift out of
// sync with what features actually exist; an unmapped key just falls back
// to a plain lock icon instead of breaking.
const FEATURE_META = {
  'dtr_records.view_all':  { icon: UsersIcon,      color: '#2E4A8C' },
  'dtr_generation.export': { icon: FileDownIcon,   color: '#0369A1' },
  'shift_management':      { icon: TimerIcon,      color: '#B45309' },
  'holiday_calendar':      { icon: CalendarIcon,   color: '#7C3AED' },
  'dtr_month_locking':     { icon: LockIcon,       color: '#B91C1C' },
  'system_health':         { icon: HeartPulseIcon, color: '#DC2626' },
  'tracking_analytics':    { icon: BarChart2Icon,  color: '#0F766E' },
  'notifications_manage':  { icon: BellIcon,       color: '#15803D' },
};
function featureMeta(key) {
  return FEATURE_META[key] ?? { icon: LockIcon, color: '#64748B' };
}

const { toast }   = useToast();
const { confirm } = useConfirm();

const ROLE_LABELS = {
  super_admin: 'Super Admin', admin: 'Admin', hr_officer: 'HR Officer',
  department_head: 'Department Head', employee: 'Faculty / Staff',
};
function roleLabel(role) { return ROLE_LABELS[role] ?? role; }
function initials(name) { return (name ?? '').split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase(); }

// ── Catalog (feature list + role defaults) ────────────────────────
const catalog = reactive({ features: {}, role_defaults: {} });
const { avatarStyle } = useAvatarColor();

// ── Employee picker ────────────────────────────────────────────────
const employees        = ref([]);
const loadingEmployees = ref(false);
const search           = ref('');
const debouncedSearch  = useDebounce(search, 300);
const selected         = ref(null);

async function loadEmployees() {
  loadingEmployees.value = true;
  try {
    const res = await axios.get('/api/employees', { params: { search: debouncedSearch.value } });
    employees.value = res.data;
  } catch {
    toast('Failed to load employees.', 'error');
  } finally {
    loadingEmployees.value = false;
  }
}
watch(debouncedSearch, loadEmployees);

// ── Selected employee's permissions ─────────────────────────────────
const perm         = reactive({ defaults: {}, effective: {}, overrides: {} });
const loadingPerm  = ref(false);
const savingKey    = ref(null);

// 'one' = only the selected employee (default, safest); 'all' = every
// employee in the system. Reset to 'one' on every new selection so leaving
// the screen in "all" mode never carries over to picking someone else.
const scope = ref('one');

function isOverridden(key) {
  return Object.prototype.hasOwnProperty.call(perm.overrides, key);
}

async function selectEmployee(emp) {
  selected.value = emp;
  scope.value = 'one';
  loadingPerm.value = true;
  try {
    const res = await axios.get(`/api/feature-permissions/${emp.id}`);
    perm.defaults  = res.data.defaults;
    perm.effective = res.data.effective;
    perm.overrides = res.data.overrides;
  } catch {
    toast('Failed to load this employee\'s permissions.', 'error');
  } finally {
    loadingPerm.value = false;
  }
}

async function toggleFeature(key) {
  const next  = !perm.effective[key];
  const label = catalog.features[key];

  if (scope.value === 'all') {
    const ok = await confirm({
      title: `${next ? 'Grant' : 'Revoke'} "${label}" for ALL employees?`,
      message: `This ${next ? 'grants' : 'revokes'} "${label}" for every employee in the system, overriding each of their role defaults. This can be undone with "Reset", but won't restore anyone's previous individual overrides.`,
      confirmText: next ? 'Grant for all' : 'Revoke for all',
      danger: true,
    });
    if (!ok) return;

    savingKey.value = key;
    try {
      const res = await axios.post('/api/feature-permissions/bulk', { feature_key: key, granted: next });
      toast(res.data.message, 'success');
      await selectEmployee(selected.value); // refresh this employee's view to reflect the bulk change
      loadHistory();
    } catch (e) {
      toast(e.response?.data?.message ?? 'Failed to update permission for all employees.', 'error');
    } finally {
      savingKey.value = null;
    }
    return;
  }

  const ok = await confirm({
    title: `${next ? 'Grant' : 'Revoke'} "${label}"?`,
    message: `This ${next ? 'grants' : 'revokes'} "${label}" for ${selected.value.name}, overriding their ${roleLabel(selected.value.role)} default.`,
    confirmText: next ? 'Grant' : 'Revoke',
    danger: !next,
  });
  if (!ok) return;

  savingKey.value = key;
  try {
    const res = await axios.post('/api/feature-permissions', {
      user_id: selected.value.id, feature_key: key, granted: next,
    });
    perm.effective = res.data.effective;
    perm.overrides = { ...perm.overrides, [key]: next };
    toast(`"${label}" ${next ? 'granted' : 'revoked'} for ${selected.value.name}.`, 'success');
    loadHistory();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to update permission.', 'error');
  } finally {
    savingKey.value = null;
  }
}

async function resetFeature(key) {
  const label = catalog.features[key];

  if (scope.value === 'all') {
    const ok = await confirm({
      title: `Reset "${label}" for ALL employees?`,
      message: `This removes every employee's custom override for "${label}", so everyone falls back to their own role's default.`,
      confirmText: 'Reset for all',
      danger: true,
    });
    if (!ok) return;

    savingKey.value = key;
    try {
      const res = await axios.delete(`/api/feature-permissions/bulk/${key}`);
      toast(res.data.message, 'success');
      await selectEmployee(selected.value);
      loadHistory();
    } catch (e) {
      toast(e.response?.data?.message ?? 'Failed to reset permission for all employees.', 'error');
    } finally {
      savingKey.value = null;
    }
    return;
  }

  const ok = await confirm({
    title: `Reset "${label}"?`,
    message: `This removes the custom override so ${selected.value.name} falls back to their ${roleLabel(selected.value.role)} default.`,
    confirmText: 'Reset',
    danger: false,
  });
  if (!ok) return;

  savingKey.value = key;
  try {
    const res = await axios.delete(`/api/feature-permissions/${selected.value.id}/${key}`);
    perm.effective = res.data.effective;
    const { [key]: _removed, ...rest } = perm.overrides;
    perm.overrides = rest;
    toast(`"${label}" reset to role default.`, 'success');
    loadHistory();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to reset permission.', 'error');
  } finally {
    savingKey.value = null;
  }
}

// ── Recent changes panel ─────────────────────────────────────────
const history        = ref([]);
const loadingHistory = ref(false);

async function loadHistory() {
  loadingHistory.value = true;
  try {
    const res = await axios.get('/api/audit-logs', { params: { table: 'feature_permissions', page: 1 } });
    history.value = res.data.data.slice(0, 8);
  } catch {
    // Non-critical panel — fail quietly.
  } finally {
    loadingHistory.value = false;
  }
}

function formatTime(dateTimeStr) {
  return new Date(dateTimeStr.replace(' ', 'T')).toLocaleString('en-PH', {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  });
}

onMounted(async () => {
  loadEmployees();
  loadHistory();
  try {
    const res = await axios.get('/api/feature-permissions/catalog');
    catalog.features      = res.data.features;
    catalog.role_defaults = res.data.role_defaults;
  } catch {
    toast('Failed to load the feature catalog.', 'error');
  }
});
</script>

<style scoped>
/* ── Employee picker column ──────────────────────────────────── */
.ac-picker {
  width: 300px; flex-shrink: 0;
  display: flex; flex-direction: column;
  border-right: 1px solid #E4E9F2;
  background: #fff;
}
.ac-emp-row {
  width: 100%;
  display: flex; align-items: center; gap: 10px;
  padding: 8px; border-radius: 10px;
  transition: background 0.12s;
}
.ac-emp-row:hover { background: #F8FAFF; }
.ac-emp-row--active { background: #EEF1FF; }
.ac-emp-avatar {
  width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #3D5CA3 0%, #1B2A5E 100%);
  color: #fff; font-size: 11px; font-weight: 700;
}
.ac-emp-avatar--lg { width: 40px; height: 40px; border-radius: 11px; font-size: 13px; }

/* ── Picker header ────────────────────────────────────────────── */
.ac-picker-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 15px 10px;
}
.ac-picker-title {
  font-size: 10px; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase;
  color: #94A3B8;
}
.ac-picker-count {
  font-size: 11px; font-weight: 700; color: #64748B;
  background: #F1F5F9; padding: 1px 7px; border-radius: 99px;
}

/* ── Panels ───────────────────────────────────────────────────── */
.ac-panel {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(27, 42, 94,0.07), 0 1px 3px rgba(27, 42, 94,0.04);
}
.ac-panel-head {
  padding: 14px 18px; border-bottom: 1px solid #F1F5F9;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
}

/* ── Scope selector (this employee vs. all employees) ────────────── */
.ac-scope-row {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: 11px 18px;
  background: #FAFBFF;
  border-bottom: 1px solid #F1F5F9;
}
.ac-scope-label { font-size: 11.5px; font-weight: 600; color: #6B7A94; flex-shrink: 0; }
.ac-scope-toggle {
  display: inline-flex; align-items: center; gap: 2px;
  background: #EEF1F8; border-radius: 9px; padding: 2px; flex-shrink: 0;
}
.ac-scope-btn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600; color: #64748B;
  padding: 5px 11px; border-radius: 7px;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}
.ac-scope-btn:hover { color: #374151; }
.ac-scope-btn--active { background: #fff; color: #2E4A8C; box-shadow: 0 1px 3px rgba(27, 42, 94,0.15); }
.ac-scope-warning {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 11.5px; font-weight: 600; color: #B45309;
  background: #FFFBEB; border: 1px solid #FDE68A;
  padding: 5px 10px; border-radius: 7px;
}

/* ── Feature rows ─────────────────────────────────────────────── */
.ac-feature-row {
  display: flex; align-items: center; gap: 14px;
  padding: 13px 18px;
  transition: background 0.12s ease;
}
.ac-feature-row:hover { background: #FAFBFF; }
.ac-feature-icon {
  width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: color-mix(in srgb, var(--f-color, #64748B) 12%, transparent);
  color: var(--f-color, #64748B);
}
.ac-override-tag {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 1px 7px 1px 5px; border-radius: 99px;
  background: #FEF9E7; color: #B45309; font-weight: 600;
}
.ac-override-dot {
  width: 5px; height: 5px; border-radius: 50%; background: #D97706; flex-shrink: 0;
}

/* ── Recent changes header icon ──────────────────────────────────── */
.ac-history-icon {
  width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(46,74,140,0.08); color: #2E4A8C;
}

.ac-reset-btn {
  font-size: 11.5px; font-weight: 600; color: #64748B;
  padding: 4px 10px; border-radius: 7px; border: 1px solid #E4E9F2;
  flex-shrink: 0; transition: background 0.12s;
}
.ac-reset-btn:hover:not(:disabled) { background: #F1F5F9; }
.ac-reset-btn:disabled { opacity: 0.5; }

/* ── Toggle switch ────────────────────────────────────────────── */
.ac-switch {
  position: relative; flex-shrink: 0;
  width: 40px; height: 22px; border-radius: 99px;
  background: #E2E8F0;
  transition: background 0.18s ease;
}
.ac-switch--on { background: #2E4A8C; }
.ac-switch:disabled { opacity: 0.6; cursor: not-allowed; }
.ac-switch-knob {
  position: absolute; top: 2px; left: 2px;
  width: 18px; height: 18px; border-radius: 50%;
  background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.25);
  transition: transform 0.18s ease;
}
.ac-switch--on .ac-switch-knob { transform: translateX(18px); }

/* ── History rows ─────────────────────────────────────────────── */
.ac-history-row { padding: 11px 18px; }

/* ── Mobile ───────────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .ac-picker { width: 100%; max-height: 260px; border-right: none; border-bottom: 1px solid #E4E9F2; }
}
</style>
