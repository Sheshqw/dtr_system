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
        <option value="LOGIN">Login</option>
        <option value="LOGIN_FAILED">Login Failed</option>
        <option value="LOGIN_BLOCKED">Login Blocked (Locked)</option>
        <option value="ACCOUNT_LOCKED">Account Locked</option>
        <option value="LOGOUT">Logout</option>
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
      <div class="p-6">

        <!-- Loading skeleton -->
        <div v-if="loading" class="bg-white rounded-xl border border-[#E4E9F2] p-5 space-y-4">
          <div v-for="i in 6" :key="i" class="flex gap-3 animate-pulse">
            <div class="w-8 h-8 rounded-full bg-slate-200 flex-shrink-0"></div>
            <div class="flex-1 space-y-1.5 pt-1">
              <div class="h-3 bg-slate-200 rounded w-64"></div>
              <div class="h-2.5 bg-slate-100 rounded w-32"></div>
            </div>
          </div>
        </div>

        <!-- Timeline -->
        <div v-else class="bg-white rounded-xl border border-[#E4E9F2] overflow-hidden shadow-[0_2px_12px_rgba(8,14,128,0.07),0_1px_3px_rgba(8,14,128,0.04)]">

          <EmptyState
            v-if="logs.length === 0"
            title="No audit activity found"
            description="No administrative actions match your current filters."
          />

          <div v-else class="tl-wrap">
            <div v-for="group in groupedLogs" :key="group.date" class="tl-group">
              <div class="tl-day-divider">
                <span>{{ group.label }}</span>
              </div>

              <div class="tl-items">
                <div v-for="log in group.items" :key="log.id" class="tl-item">
                  <span class="tl-dot" :style="{ background: actionMeta(log.action).bg, color: actionMeta(log.action).color }">
                    <component :is="actionMeta(log.action).icon" class="w-3.5 h-3.5" />
                  </span>
                  <div class="tl-content">
                    <p class="tl-text">
                      <span class="tl-user">{{ log.user }}</span>
                      {{ actionMeta(log.action).verb }}
                      <template v-if="!AUTH_ACTIONS.includes(log.action)">
                        <span class="tl-table">{{ tableLabel(log.table) }}</span>
                        <span v-if="log.record_id" class="tl-record">#{{ log.record_id }}</span>
                      </template>
                    </p>
                    <p v-if="log.reason" class="tl-reason">"{{ log.reason }}"</p>
                    <p class="tl-meta">
                      <span>{{ formatTime(log.created_at) }}</span>
                      <span v-if="isToday(log.created_at)" class="tl-relative">· {{ relativeTime(log.created_at) }}</span>
                      <span class="tl-ip" :title="'IP: ' + log.ip">{{ log.ip }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Pagination -->
        <PaginationBar
          v-if="meta"
          :current-page="meta.current_page"
          :last-page="meta.last_page"
          :total="meta.total"
          :from="meta.from"
          :to="meta.to"
          @go="load"
          class="mt-3"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import {
  ShieldCheck as ShieldCheckIcon,
  Plus        as PlusIcon,
  Pencil      as PencilIcon,
  Trash2      as Trash2Icon,
  UserX       as UserXIcon,
  Check       as CheckIcon,
  X           as XIcon,
  Activity    as ActivityIcon,
  LogIn       as LogInIcon,
  LogOut      as LogOutIcon,
  ShieldAlert as ShieldAlertIcon,
  Lock        as LockIcon,
} from 'lucide-vue-next';

const logs    = ref([]);
const meta    = ref(null);
const loading = ref(false);

const filters = reactive({ date: '', action: '', table: '' });

// ── Action → icon, color, and verb (drives both the dot and the sentence) ──
const ACTION_META = {
  CREATE:        { icon: PlusIcon,     color: '#059669', bg: '#ECFDF5', verb: 'created' },
  UPDATE:        { icon: PencilIcon,   color: '#D97706', bg: '#FFFBEB', verb: 'updated' },
  DELETE:        { icon: Trash2Icon,   color: '#DC2626', bg: '#FEF2F2', verb: 'deleted' },
  DEACTIVATE:    { icon: UserXIcon,    color: '#64748B', bg: '#F1F5F9', verb: 'deactivated' },
  APPROVE:       { icon: CheckIcon,    color: '#2563EB', bg: '#EFF6FF', verb: 'approved' },
  DENY:          { icon: XIcon,        color: '#EA580C', bg: '#FFF7ED', verb: 'denied' },
  LOGIN:         { icon: LogInIcon,    color: '#059669', bg: '#ECFDF5', verb: 'logged in' },
  LOGOUT:        { icon: LogOutIcon,   color: '#64748B', bg: '#F1F5F9', verb: 'logged out' },
  LOGIN_FAILED:  { icon: ShieldAlertIcon, color: '#D97706', bg: '#FFFBEB', verb: 'failed to log in' },
  LOGIN_BLOCKED: { icon: LockIcon,     color: '#EA580C', bg: '#FFF7ED', verb: 'tried to log in while locked out' },
  ACCOUNT_LOCKED: { icon: LockIcon,    color: '#DC2626', bg: '#FEF2F2', verb: 'was locked out after 5 failed attempts' },
};

// These read as a complete sentence on their own ("X logged in") — the
// generic CRUD "verb + table + #id" tail doesn't apply to them.
const AUTH_ACTIONS = ['LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'LOGIN_BLOCKED', 'ACCOUNT_LOCKED'];

function actionMeta(action) {
  return ACTION_META[action] ?? { icon: ActivityIcon, color: '#64748B', bg: '#F1F5F9', verb: action?.toLowerCase() };
}

const TABLE_LABELS = {
  employees:        'an employee record',
  attendance_logs:  'an attendance log',
  leave_requests:   'a leave request',
  holidays:         'a holiday',
  shifts:           'a shift',
  departments:      'a department',
};
function tableLabel(table) {
  return TABLE_LABELS[table] ?? (table ?? 'a record').replace(/_/g, ' ');
}

// ── Day-grouped timeline ─────────────────────────────────────────────────
// `logs` already arrives newest-first from the API, so grouping preserves
// that order — no re-sorting needed.
const groupedLogs = computed(() => {
  const groups = [];
  const byDate = new Map();
  for (const log of logs.value) {
    const dateKey = log.created_at.slice(0, 10);
    if (!byDate.has(dateKey)) {
      const group = { date: dateKey, label: dayLabel(dateKey), items: [] };
      byDate.set(dateKey, group);
      groups.push(group);
    }
    byDate.get(dateKey).items.push(log);
  }
  return groups;
});

function dayLabel(dateStr) {
  const d = new Date(`${dateStr}T00:00:00`);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
  if (d.getTime() === today.getTime())     return 'Today';
  if (d.getTime() === yesterday.getTime()) return 'Yesterday';
  return d.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function isToday(dateTimeStr) {
  return dateTimeStr.slice(0, 10) === new Date().toISOString().slice(0, 10);
}

function formatTime(dateTimeStr) {
  return new Date(dateTimeStr.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
}

function relativeTime(dateTimeStr) {
  const diff = Math.floor((Date.now() - new Date(dateTimeStr.replace(' ', 'T')).getTime()) / 1000);
  if (diff < 60)    return 'just now';
  if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

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

/* ── Timeline ─────────────────────────────────────────── */
.tl-wrap { padding: 20px 22px 22px; }
.tl-group + .tl-group { margin-top: 22px; }

.tl-day-divider {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 12px;
}
.tl-day-divider::after {
  content: ''; flex: 1; height: 1px; background: #EEF2FA;
}
.tl-day-divider span {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  color: #94A3B8;
}

.tl-items { position: relative; }
/* Continuous connecting line behind the dots, spanning the group */
.tl-items::before {
  content: ''; position: absolute; left: 15px; top: 4px; bottom: 4px;
  width: 2px; background: #EEF2FA;
}

.tl-item {
  position: relative;
  display: flex; align-items: flex-start; gap: 14px;
  padding: 9px 6px;
  border-radius: 10px;
  transition: background 0.12s;
}
.tl-item:hover { background: #FAFBFF; }

.tl-dot {
  position: relative; z-index: 1;
  width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 0 4px #fff; /* surface ring — separates the dot from the line behind it */
}

.tl-content { flex: 1; min-width: 0; padding-top: 2px; }
.tl-text { font-size: 13px; color: #334155; line-height: 1.5; }
.tl-user { font-weight: 700; color: #0F172A; }
.tl-table { font-weight: 600; color: #0F172A; }
.tl-record { font-family: ui-monospace, monospace; font-size: 11.5px; color: #94A3B8; margin-left: 2px; }

.tl-reason {
  font-size: 12px; color: #64748B; font-style: italic;
  margin-top: 2px; max-width: 520px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.tl-meta {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; color: #94A3B8; margin-top: 3px;
}
.tl-relative { color: #B0BAD1; }
.tl-ip { margin-left: auto; font-family: ui-monospace, monospace; color: #C4CCE0; }

/* ── Mobile ─────────────────────────────────────────── */
@media (max-width: 768px) {
  .px-6.py-3.border-b { flex-wrap: wrap; gap: 8px; }
}
@media (max-width: 640px) {
  .tl-wrap { padding: 14px 14px 16px; }
  .tl-ip   { display: none; }
}
</style>
