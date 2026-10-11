<template>
  <Teleport to="body">
    <Transition name="cp-fade">
      <div v-if="isOpen" class="cp-overlay" @click.self="close" @keydown.esc="close">
        <Transition name="cp-pop" appear>
          <div class="cp-panel" role="dialog" aria-modal="true" aria-label="Quick search">
            <div class="cp-input-row">
              <SearchIcon class="w-4 h-4 flex-shrink-0" style="color:#6B7A94;" />
              <input
                ref="inputEl"
                v-model="query"
                type="text"
                placeholder="Search employees or jump to a page…"
                class="cp-input"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="activateSelected"
              />
              <kbd class="cp-esc">esc</kbd>
            </div>

            <div class="cp-results" ref="resultsEl">
              <div v-if="matchedPages.length" class="cp-section">
                <p class="cp-section-label">Pages</p>
                <button
                  v-for="(item, i) in matchedPages"
                  :key="`page-${item.name}`"
                  class="cp-item"
                  :class="{ 'cp-item--active': flatIndex('page', i) === activeIndex }"
                  @mouseenter="activeIndex = flatIndex('page', i)"
                  @click="go(item)"
                >
                  <span class="cp-item-icon"><component :is="item.icon" class="w-4 h-4" /></span>
                  <span class="cp-item-label">{{ item.label }}</span>
                  <ArrowRightIcon class="w-3.5 h-3.5 cp-item-arrow" />
                </button>
              </div>

              <div v-if="query.trim().length >= 2" class="cp-section">
                <p class="cp-section-label">Employees</p>
                <div v-if="searching" class="cp-loading">
                  <Loader2Icon class="w-4 h-4 animate-spin" style="color:#6B7A94;" />
                </div>
                <template v-else-if="employees.length">
                  <button
                    v-for="(emp, i) in employees"
                    :key="`emp-${emp.id}`"
                    class="cp-item"
                    :class="{ 'cp-item--active': flatIndex('emp', i) === activeIndex }"
                    @mouseenter="activeIndex = flatIndex('emp', i)"
                    @click="goToEmployee(emp)"
                  >
                    <span class="cp-avatar" :style="avatarStyle(emp.name)">{{ initials(emp.name) }}</span>
                    <span class="cp-item-body">
                      <span class="cp-item-label">{{ emp.name }}</span>
                      <span class="cp-item-sub">{{ emp.employee_id }}<template v-if="emp.department"> · {{ emp.department }}</template></span>
                    </span>
                    <ArrowRightIcon class="w-3.5 h-3.5 cp-item-arrow" />
                  </button>
                </template>
                <p v-else class="cp-empty">No employees match "{{ query }}"</p>
              </div>

              <p v-if="!matchedPages.length && query.trim().length < 2" class="cp-empty">
                No pages match "{{ query }}"
              </p>
            </div>

            <div class="cp-footer">
              <span class="cp-hint"><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
              <span class="cp-hint"><kbd>↵</kbd> select</span>
              <span class="cp-hint"><kbd>esc</kbd> close</span>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { usePermissionsStore } from '@/stores/permissions';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { useDebounce } from '@/composables/useDebounce';
import { useAvatarColor } from '@/composables/useAvatarColor';
import {
  Search           as SearchIcon,
  ArrowRight       as ArrowRightIcon,
  Loader2          as Loader2Icon,
  LayoutDashboard  as LayoutDashboardIcon,
  Clock            as ClockIcon,
  Users            as UsersIcon,
  BarChart2        as BarChart2Icon,
  FileText         as FileTextIcon,
  Calendar         as CalendarIcon,
  ShieldCheck      as ShieldCheckIcon,
  Wifi             as WifiIcon,
  Timer            as TimerIcon,
  Building2        as Building2Icon,
  Database         as DatabaseIcon,
  MapPin           as MapPinIcon,
  HeartPulse       as HeartPulseIcon,
  Settings         as SettingsIcon,
} from 'lucide-vue-next';

const { isOpen, close, toggle } = useCommandPalette();
const router  = useRouter();
const auth    = useAuthStore();
const perms   = usePermissionsStore();
const { avatarStyle } = useAvatarColor();

const query      = ref('');
const inputEl    = ref(null);
const resultsEl  = ref(null);
const activeIndex = ref(0);
const employees  = ref([]);
const searching  = ref(false);

const debouncedQuery = useDebounce(query, 220);

// ── Page directory — mirrors the sidebar's role gating, plus the same
// per-employee feature grants the router enforces (meta.requiresFeature),
// so the palette never offers a page that would just bounce the user. ──
const PAGES = [
  { name: 'dashboard',      label: 'Dashboard',      icon: LayoutDashboardIcon, roles: 'all' },
  { name: 'attendance',     label: 'Attendance',     icon: ClockIcon,           roles: 'all' },
  { name: 'employees',      label: 'Employee Logs',  icon: UsersIcon,           roles: 'all', feature: 'dtr_records.view_all' },
  { name: 'tracking',       label: 'Tracking',       icon: BarChart2Icon,       roles: 'all', feature: 'tracking_analytics' },
  { name: 'reports',        label: 'Reports',        icon: FileTextIcon,        roles: 'all' },
  { name: 'holidays',       label: 'Holidays',       icon: CalendarIcon,        roles: ['super_admin', 'admin', 'hr_officer'], feature: 'holiday_calendar' },
  { name: 'audit',          label: 'Audit Trail',    icon: ShieldCheckIcon,     roles: ['super_admin', 'admin', 'hr_officer'] },
  { name: 'biometric',      label: 'Biometric',      icon: WifiIcon,            roles: ['super_admin', 'admin', 'hr_officer'] },
  { name: 'shifts',         label: 'Shifts',         icon: TimerIcon,           roles: ['super_admin', 'admin'], feature: 'shift_management' },
  { name: 'departments',    label: 'Departments',    icon: Building2Icon,       roles: ['super_admin', 'admin'] },
  { name: 'backup',         label: 'Backup',         icon: DatabaseIcon,        roles: ['super_admin', 'admin'] },
  { name: 'campuses',       label: 'Campuses',       icon: MapPinIcon,          roles: ['super_admin'] },
  { name: 'admin-accounts', label: 'Admin Accounts', icon: ShieldCheckIcon,     roles: ['super_admin'] },
  { name: 'system-health',  label: 'System Health',  icon: HeartPulseIcon,      roles: ['super_admin'], feature: 'system_health' },
  { name: 'settings',       label: 'Settings',       icon: SettingsIcon,        roles: ['super_admin'] },
];

const availablePages = computed(() => {
  const role = auth.employee?.role;
  return PAGES.filter(p =>
    (p.roles === 'all' || p.roles.includes(role)) &&
    (!p.feature || perms.can(p.feature))
  );
});

const matchedPages = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return availablePages.value;
  return availablePages.value.filter(p => p.label.toLowerCase().includes(q));
});

// ── Combined keyboard-navigable index across both sections ─────────────
function flatIndex(section, i) {
  return section === 'page' ? i : matchedPages.value.length + i;
}
const totalResults = computed(() => matchedPages.value.length + employees.value.length);

function move(delta) {
  if (!totalResults.value) return;
  activeIndex.value = (activeIndex.value + delta + totalResults.value) % totalResults.value;
  nextTick(() => {
    resultsEl.value?.querySelector('.cp-item--active')?.scrollIntoView({ block: 'nearest' });
  });
}

function activateSelected() {
  if (activeIndex.value < matchedPages.value.length) {
    go(matchedPages.value[activeIndex.value]);
  } else {
    const emp = employees.value[activeIndex.value - matchedPages.value.length];
    if (emp) goToEmployee(emp);
  }
}

function go(page) {
  close();
  router.push({ name: page.name });
}

function goToEmployee(emp) {
  close();
  router.push({ name: 'employees', query: { q: emp.employee_id } });
}

function initials(name) {
  return name?.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase() ?? '?';
}

async function searchEmployees(term) {
  if (term.trim().length < 2) { employees.value = []; return; }
  searching.value = true;
  try {
    const res = await axios.get('/api/employees', { params: { search: term, page: 1, per_page: 6 } });
    employees.value = res.data.data ?? [];
  } catch {
    employees.value = [];
  } finally {
    searching.value = false;
  }
}

watch(debouncedQuery, (val) => searchEmployees(val));
watch(query, () => { activeIndex.value = 0; });

watch(isOpen, async (open) => {
  if (open) {
    query.value = '';
    employees.value = [];
    activeIndex.value = 0;
    await nextTick();
    inputEl.value?.focus();
  }
});

// ── Global Ctrl+K / Cmd+K shortcut ──────────────────────────────────────
function onGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    toggle();
  }
}
onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown));
</script>

<style scoped>
.cp-overlay {
  position: fixed; inset: 0; z-index: 100;
  background: rgba(8,14,40,0.55);
  backdrop-filter: blur(2px);
  display: flex; align-items: flex-start; justify-content: center;
  padding: 12vh 16px 16px;
}

.cp-panel {
  width: 100%; max-width: 560px;
  background: #fff; border-radius: 16px;
  box-shadow: 0 24px 60px rgba(8,14,80,0.35), 0 4px 16px rgba(8,14,80,0.15);
  overflow: hidden;
  display: flex; flex-direction: column;
  max-height: 70vh;
}

.cp-input-row {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 18px;
  border-bottom: 1px solid #F1F5F9;
  flex-shrink: 0;
}
.cp-input {
  flex: 1; border: none; outline: none;
  font-size: 15px; color: #0F172A; background: transparent;
}
.cp-input::placeholder { color: #6B7A94; }
.cp-esc {
  font-size: 10px; font-weight: 600; color: #6B7A94;
  border: 1px solid #E4E9F2; border-radius: 5px; padding: 2px 6px;
  font-family: inherit; flex-shrink: 0;
}

.cp-results { overflow-y: auto; padding: 6px; flex: 1; }
.cp-section + .cp-section { margin-top: 4px; }
.cp-section-label {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
  color: #6B7A94; padding: 8px 10px 4px;
}

.cp-item {
  width: 100%; display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 10px; text-align: left;
  transition: background 0.1s;
}
.cp-item--active { background: #EEF2FF; }
.cp-item-icon {
  width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: #F1F5F9; color: #475569;
}
.cp-item--active .cp-item-icon { background: #E0E7FF; color: #4338CA; }
.cp-item-label { font-size: 13.5px; font-weight: 600; color: #0F172A; flex: 1; }
.cp-item-arrow { color: #C7D2FE; flex-shrink: 0; opacity: 0; transition: opacity 0.1s; }
.cp-item--active .cp-item-arrow { opacity: 1; }

.cp-avatar {
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 11px; font-weight: 700;
}
.cp-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.cp-item-sub { font-size: 11px; color: #6B7A94; font-family: ui-monospace, monospace; }

.cp-loading, .cp-empty { padding: 16px 10px; text-align: center; font-size: 12.5px; color: #6B7A94; }

.cp-footer {
  display: flex; align-items: center; gap: 14px;
  padding: 9px 16px; border-top: 1px solid #F1F5F9;
  background: #FAFBFF; flex-shrink: 0;
}
.cp-hint { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; color: #6B7A94; }
.cp-hint kbd {
  font-family: inherit; font-size: 10px; font-weight: 600; color: #64748B;
  background: #fff; border: 1px solid #E4E9F2; border-radius: 4px; padding: 1px 5px;
}

.cp-fade-enter-active, .cp-fade-leave-active { transition: opacity 0.15s ease; }
.cp-fade-enter-from, .cp-fade-leave-to { opacity: 0; }
.cp-pop-enter-active { transition: opacity 0.15s ease, transform 0.15s cubic-bezier(0.22,1,0.36,1); }
.cp-pop-enter-from { opacity: 0; transform: translateY(-8px) scale(0.98); }

@media (max-width: 640px) {
  .cp-overlay { padding: 8vh 12px 12px; }
}
</style>
