<template>
  <div class="flex h-screen overflow-hidden" style="background: #F4F6FB;">

    <!-- ── Mobile top bar (hidden on desktop) ───────────────────── -->
    <header
      class="lg:hidden fixed top-0 left-0 right-0 z-30 flex items-center gap-3 px-4 h-14"
      style="background: linear-gradient(135deg, #1B2A5E, #2A3C76); border-bottom: 1px solid rgba(255,255,255,0.08);"
    >
      <button
        @click="sidebarOpen = true"
        aria-label="Open navigation"
        class="p-2 rounded-lg transition-colors"
        style="color: #9DADD8;"
      >
        <MenuIcon class="w-5 h-5" />
      </button>

      <div class="flex items-center gap-2.5 flex-1">
        <img :src="settings.logoUrl" :alt="settings.systemName" class="w-7 h-7 rounded-full object-cover flex-shrink-0"
          style="box-shadow: 0 0 0 1.5px rgba(201, 162, 39,0.6);" />
        <div class="leading-tight">
          <p class="text-white text-xs font-bold tracking-wide">{{ settings.systemName }}</p>
          <p class="text-[9px] font-semibold tracking-widest uppercase" style="color: #C9A227;">{{ settings.shortName }}</p>
        </div>
      </div>

      <!-- Mobile user avatar -->
      <div
        class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold"
        style="background: linear-gradient(135deg, #C9A227, #B8911F);"
        aria-hidden="true"
      >{{ initials }}</div>
    </header>

    <!-- ── Backdrop (mobile only) ────────────────────────────────── -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="lg:hidden fixed inset-0 z-40 bg-black/50 backdrop-blur-sm"
        @click="sidebarOpen = false"
        aria-hidden="true"
      ></div>
    </Transition>

    <!-- ── Sidebar ───────────────────────────────────────────────── -->
    <aside
      class="fixed lg:relative inset-y-0 left-0 z-50 w-64 flex-shrink-0 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      style="background: linear-gradient(180deg, #1B2A5E 0%, #24356F 45%, #2A3C76 100%); box-shadow: 4px 0 28px rgba(27, 42, 94,0.28), 1px 0 0 rgba(27, 42, 94,0.06);"
      aria-label="Main navigation"
    >
      <!-- Dot-grid texture -->
      <div
        class="absolute inset-0 pointer-events-none"
        style="background-image: radial-gradient(rgba(255,255,255,0.035) 1px, transparent 1px); background-size: 18px 18px;"
      ></div>

      <!-- Brand -->
      <div class="relative px-5 py-5 border-b border-white/10 flex items-center justify-between">
        <!-- Campus color indicator strip at top of brand area -->
        <div
          class="absolute top-0 left-0 right-0 h-0.5"
          :style="{ background: campus.heroBand, opacity: '0.7' }"
        ></div>

        <div class="flex items-center gap-3">
          <div class="relative flex-shrink-0">
            <img
              :src="settings.logoUrl"
              :alt="settings.systemName"
              class="w-10 h-10 rounded-full object-cover shadow-lg"
              :style="{ boxShadow: `0 0 0 2px ${campus.heroBand}88, 0 4px 12px rgba(0,0,0,0.4)` }"
            />
            <!-- Campus color glow ring -->
            <div
              class="absolute inset-0 rounded-full pointer-events-none"
              :style="{ boxShadow: `0 0 10px ${campus.heroBand}40` }"
            ></div>
          </div>
          <div class="min-w-0">
            <p class="text-white text-sm font-bold leading-tight tracking-wide">{{ settings.systemName }}</p>
            <p
              class="text-[11px] font-semibold tracking-widest uppercase leading-tight transition-colors duration-300"
              :style="{ color: campus.heroBand }"
            >{{ campusName }}</p>
            <div class="flex items-center gap-1.5 mt-1">
              <span class="text-[9px] leading-tight" style="color: rgba(157, 173, 216,0.6);">{{ settings.shortName }}</span>
              <span class="version-chip">v1.0</span>
            </div>
          </div>
        </div>

        <!-- Close button (mobile only) -->
        <button
          @click="sidebarOpen = false"
          class="lg:hidden p-1.5 rounded-lg transition-colors flex-shrink-0"
          style="color: rgba(157, 173, 216,0.7);"
          aria-label="Close navigation"
        >
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Quick search trigger -->
      <div class="relative px-3 pt-3">
        <button class="quick-search-btn" @click="palette.open()">
          <SearchIcon class="w-3.5 h-3.5" />
          <span>Quick search</span>
          <kbd class="quick-search-kbd">Ctrl K</kbd>
        </button>
      </div>

      <!-- Nav links -->
      <nav class="relative flex-1 px-3 py-4 overflow-y-auto" style="space-y: 0;">

        <!-- Section label: Main -->
        <p class="nav-section-label">Main</p>

        <RouterLink
          v-for="item in visibleNavItems"
          :key="item.name"
          :to="item.to"
          class="nav-link group"
          :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
          @click="sidebarOpen = false"
        >
          <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-idle'">
            <component :is="item.icon" class="w-5 h-5" />
          </span>
          <span class="nav-label">{{ item.label }}</span>
          <span
            v-if="item.name === 'reports' && pendingPrintRequests > 0"
            class="nav-badge"
          >{{ pendingPrintRequests > 99 ? '99+' : pendingPrintRequests }}</span>
          <span v-if="$route.name === item.name" class="nav-pip" />
        </RouterLink>

        <!-- Admin section -->
        <template v-if="isAdminOrHr">
          <p class="nav-section-label" style="margin-top: 18px;">Management</p>

          <RouterLink
            v-for="item in hrItems"
            :key="item.name"
            :to="item.to"
            class="nav-link group"
            :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
            @click="sidebarOpen = false"
          >
            <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-idle'">
              <component :is="item.icon" class="w-5 h-5" />
            </span>
            <span class="nav-label">{{ item.label }}</span>
            <span v-if="$route.name === item.name" class="nav-pip" />
          </RouterLink>

          <template v-if="isAdmin">
            <p class="nav-section-label" style="margin-top: 18px;">Administration</p>
            <RouterLink
              v-for="item in adminOnlyItems"
              :key="item.name"
              :to="item.to"
              class="nav-link group"
              :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
              @click="sidebarOpen = false"
            >
              <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-idle'">
                <component :is="item.icon" class="w-5 h-5" />
              </span>
              <span class="nav-label">{{ item.label }}</span>
              <span v-if="$route.name === item.name" class="nav-pip" />
            </RouterLink>
          </template>

          <template v-if="isSuperAdmin">
            <p class="nav-section-label" style="margin-top: 18px; color: rgba(201, 162, 39,0.55);">Super Admin</p>
            <RouterLink
              v-for="item in superAdminItems"
              :key="item.name"
              :to="item.to"
              class="nav-link group"
              :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
              @click="sidebarOpen = false"
            >
              <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-gold'">
                <component :is="item.icon" class="w-5 h-5" />
              </span>
              <span class="nav-label">{{ item.label }}</span>
              <span v-if="$route.name === item.name" class="nav-pip" />
            </RouterLink>
          </template>
        </template>
      </nav>

      <!-- Gold divider -->
      <div class="relative mx-4 h-px" style="background: linear-gradient(90deg, transparent, rgba(201, 162, 39,0.35), transparent);"></div>

      <!-- Appearance and Text Size moved to the top-right settings popover
           (see PageHeader.vue) — kept the sidebar focused on navigation and
           freed up vertical space for it. -->

      <!-- User card -->
      <div class="relative px-4 pb-4">
        <div
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl"
          style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 shadow-md"
            style="background: linear-gradient(135deg, #C9A227, #B8911F);"
          >
            <span class="text-white text-xs font-bold">{{ initials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-xs font-semibold truncate leading-tight">{{ auth.employee?.name }}</p>
            <p class="text-[10px] truncate capitalize mt-0.5 leading-tight" style="color: #9DADD8;">
              {{ roleLabel(auth.employee?.role) }}
            </p>
          </div>
          <button @click="openSessions" class="changepwd-btn flex-shrink-0 p-1.5 rounded-md transition-colors" title="Active sessions" aria-label="Active sessions">
            <MonitorSmartphoneIcon class="w-4 h-4" />
          </button>
          <button @click="pwdModal = true" class="changepwd-btn flex-shrink-0 p-1.5 rounded-md transition-colors" title="Change password" aria-label="Change password">
            <KeyIcon class="w-4 h-4" />
          </button>
          <button @click="handleLogout" class="logout-btn flex-shrink-0 p-1.5 rounded-md transition-colors" title="Sign out" aria-label="Sign out">
            <LogOutIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ── Main content ───────────────────────────────────────────── -->
    <main ref="mainEl" class="flex-1 overflow-y-auto relative pt-14 lg:pt-0">
      <!-- No mode="out-in": that mode makes the incoming page wait for the
           outgoing one's leave transition to fully finish first — clicking
           through several sidebar links quickly interrupts that sequence
           before it completes, and Vue can be left with a component stuck
           mid-transition (opacity: 0 from .page-leave-to/.page-enter-from
           never getting cleared) — a blank page with no error, since
           nothing crashed, it's just invisible. Letting enter/leave run
           simultaneously has no such handoff to get stuck on. -->
      <RouterView v-slot="{ Component }">
        <Transition name="page">
          <component :is="Component" :key="$route.name" />
        </Transition>
      </RouterView>
    </main>
  </div>

  <!-- ── Change Password Modal ─────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="pwdModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closePwdModal" />
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-[#EEF1FF] flex items-center justify-center flex-shrink-0">
            <KeyIcon class="w-5 h-5 text-[#2E4A8C]" />
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-[#0F172A]">Change Password</h2>
            <p class="text-xs text-[#64748B]">Update your account password</p>
          </div>
          <button @click="closePwdModal" class="text-[#6B7A94] hover:text-[#0F172A] transition-colors" aria-label="Close">
            <XIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="submitPwd" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Current Password</label>
            <input v-model="pwdForm.current_password" type="password" required placeholder="Enter current password"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] bg-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">New Password</label>
            <input v-model="pwdForm.password" type="password" required minlength="8" placeholder="8+ characters, with letters and numbers"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] bg-white" />
            <p class="text-xs mt-1" style="color:#6B7A94;">Must include at least one letter and one number.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Confirm New Password</label>
            <input v-model="pwdForm.password_confirmation" type="password" required placeholder="Repeat new password"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] bg-white" />
          </div>
          <div v-if="pwdError" class="text-sm text-[#EF4444] bg-red-50 border border-red-100 rounded-lg px-3 py-2">
            {{ pwdError }}
          </div>
          <div class="flex gap-3 pt-1">
            <button type="button" @click="closePwdModal"
              class="flex-1 px-4 py-2 text-sm font-medium border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
              Cancel
            </button>
            <button type="submit" :disabled="pwdSaving"
              class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#2E4A8C] hover:bg-[#3D5CA3] text-white text-sm font-medium rounded-lg disabled:opacity-60 transition-colors">
              <Loader2Icon v-if="pwdSaving" class="w-4 h-4 animate-spin" />
              Save Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Active Sessions Modal ─────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="sessionsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="sessionsModal = false" />
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-[#EEF1FF] flex items-center justify-center flex-shrink-0">
            <MonitorSmartphoneIcon class="w-5 h-5 text-[#2E4A8C]" />
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-[#0F172A]">Active Sessions</h2>
            <p class="text-xs text-[#64748B]">Devices currently signed in to your account</p>
          </div>
          <button @click="sessionsModal = false" class="text-[#6B7A94] hover:text-[#0F172A] transition-colors" aria-label="Close">
            <XIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="px-6 py-4 max-h-80 overflow-y-auto space-y-2">
          <div v-if="sessionsLoading" class="py-10 text-center">
            <Loader2Icon class="w-5 h-5 animate-spin mx-auto" style="color:#6B7A94;" />
          </div>
          <div
            v-else
            v-for="s in sessions"
            :key="s.id"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg"
            style="border:1px solid #E2E8F0;"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#F1F5F9;">
              <MonitorSmartphoneIcon class="w-4 h-4" style="color:#64748B;" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-[#0F172A] truncate">{{ s.name || 'Unknown device' }}</p>
              <p class="text-xs" :style="s.is_current ? 'color:#059669;font-weight:600;' : 'color:#6B7A94;'">
                {{ s.is_current ? 'This device' : `Active ${s.last_used_at ?? 'recently'}` }}
              </p>
            </div>
            <button
              v-if="!s.is_current"
              @click="revokeSession(s.id)"
              :disabled="revokingId === s.id"
              class="text-xs font-semibold px-2.5 py-1.5 rounded-md transition-colors disabled:opacity-50 flex-shrink-0"
              style="background:#FEF2F2;color:#DC2626;"
            >
              {{ revokingId === s.id ? '…' : 'Revoke' }}
            </button>
          </div>
        </div>

        <div class="px-6 pb-5 pt-1">
          <button
            v-if="sessions.filter(s => !s.is_current).length > 0"
            @click="revokeOtherSessions"
            class="w-full py-2 rounded-lg text-sm font-semibold transition-colors"
            style="background:#FEF2F2;color:#DC2626;"
          >
            Sign out all other devices
          </button>
          <p v-else-if="!sessionsLoading" class="text-xs text-center" style="color:#6B7A94;">
            No other active sessions — you're only signed in here.
          </p>
        </div>
      </div>
    </div>
  </Teleport>

  <ToastContainer />
  <CommandPalette />
  <ConfirmDialog />
</template>

<script setup>
import { computed, ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import axios from 'axios';
import {
  LayoutDashboard as LayoutDashboardIcon,
  Clock          as ClockIcon,
  Users          as UsersIcon,
  FileText       as FileTextIcon,
  BarChart2      as BarChart2Icon,
  LogOut         as LogOutIcon,
  Calendar       as CalendarIcon,
  Timer          as TimerIcon,
  Building2      as Building2Icon,
  MapPin         as MapPinIcon,
  ShieldCheck    as ShieldCheckIcon,
  Database       as DatabaseIcon,
  Key            as KeyIcon,
  X              as XIcon,
  Loader2        as Loader2Icon,
  Wifi           as WifiIcon,
  Menu           as MenuIcon,
  HeartPulse     as HeartPulseIcon,
  Search         as SearchIcon,
  MonitorSmartphone as MonitorSmartphoneIcon,
  Settings       as SettingsIcon,
  Lock           as LockIcon,
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import { usePermissionsStore } from '@/stores/permissions';
import { useToast } from '@/composables/useToast';
import { useCampusIdentity } from '@/composables/useCampusIdentity';
import ToastContainer from '@/components/ToastContainer.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';

const auth     = useAuthStore();
const perms    = usePermissionsStore();
const palette  = useCommandPalette();

const router = useRouter();

// <main> is the scroll container (not the window), so vue-router's own
// scroll handling never touches it — without this, opening a page from the
// sidebar while scrolled down left the new page scrolled down too.
const mainEl = ref(null);
const stopScrollReset = router.afterEach((to, from) => {
  if (to.name !== from.name) mainEl.value?.scrollTo({ top: 0 });
});
onBeforeUnmount(stopScrollReset);
const settings = useSettingsStore();
const { toast } = useToast();
const { campus, campusName } = useCampusIdentity();

// ── Mobile sidebar state ──────────────────────────────────────────
const sidebarOpen = ref(false);

// ── Change Password ───────────────────────────────────────────────
const pwdModal  = ref(false);
const pwdSaving = ref(false);
const pwdError  = ref('');
const pwdForm   = reactive({ current_password: '', password: '', password_confirmation: '' });

function closePwdModal() {
  pwdModal.value = false;
  pwdError.value = '';
  Object.assign(pwdForm, { current_password: '', password: '', password_confirmation: '' });
}

async function submitPwd() {
  pwdError.value  = '';
  pwdSaving.value = true;
  try {
    const res      = await axios.post('/api/auth/change-password', pwdForm);
    const newToken = res.data.token;
    auth.token     = newToken;
    localStorage.setItem('dtr_token', newToken);
    axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
    closePwdModal();
    toast('Password changed successfully.', 'success');
  } catch (e) {
    pwdError.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? e.response?.data?.message
      ?? 'Failed to change password.';
  } finally {
    pwdSaving.value = false;
  }
}

// ── Active Sessions ──────────────────────────────────────────────
const sessionsModal   = ref(false);
const sessions        = ref([]);
const sessionsLoading = ref(false);
const revokingId      = ref(null);

async function openSessions() {
  sessionsModal.value = true;
  sessionsLoading.value = true;
  try {
    const res = await axios.get('/api/auth/sessions');
    sessions.value = res.data;
  } catch {
    toast('Failed to load active sessions.', 'error');
  } finally {
    sessionsLoading.value = false;
  }
}

async function revokeSession(id) {
  revokingId.value = id;
  try {
    await axios.delete(`/api/auth/sessions/${id}`);
    sessions.value = sessions.value.filter(s => s.id !== id);
    toast('Session revoked.', 'success');
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to revoke session.', 'error');
  } finally {
    revokingId.value = null;
  }
}

async function revokeOtherSessions() {
  try {
    const res = await axios.post('/api/auth/sessions/revoke-others');
    sessions.value = sessions.value.filter(s => s.is_current);
    toast(res.data.message, 'success');
  } catch {
    toast('Failed to revoke other sessions.', 'error');
  }
}

// ── Role labels ───────────────────────────────────────────────────
const ROLE_LABELS = {
  super_admin:     'Super Admin',
  admin:           'Admin',
  hr_officer:      'HR Officer',
  department_head: 'Department Head',
  employee:        'Faculty / Staff',
};
function roleLabel(role) {
  return ROLE_LABELS[role] ?? (role?.replace(/_/g, ' ') ?? '');
}

// ── Nav items ─────────────────────────────────────────────────────
// 'requiresFeature' mirrors the router's own meta.requiresFeature guard
// (resources/js/router/index.js) — without this, a plain employee would
// still see the link even though clicking it just bounces them back.
const navItems = [
  { name: 'dashboard',   label: 'Dashboard',    to: { name: 'dashboard'   }, icon: LayoutDashboardIcon },
  { name: 'attendance',  label: 'Attendance',   to: { name: 'attendance'  }, icon: ClockIcon           },
  { name: 'employees',   label: 'Employee Logs', to: { name: 'employees'   }, icon: UsersIcon, requiresFeature: 'dtr_records.view_all' },
  { name: 'tracking',    label: 'Tracking',     to: { name: 'tracking'    }, icon: BarChart2Icon, requiresFeature: 'tracking_analytics' },
  { name: 'reports',     label: 'Reports',      to: { name: 'reports'     }, icon: FileTextIcon        },
];
const visibleNavItems = computed(() =>
  navItems.filter(item => !item.requiresFeature || perms.can(item.requiresFeature))
);

const hrItems = [
  { name: 'shifts',    label: 'Shifts',      to: { name: 'shifts'    }, icon: TimerIcon       },
  { name: 'holidays',  label: 'Holidays',    to: { name: 'holidays'  }, icon: CalendarIcon    },
  { name: 'audit',     label: 'Audit Trail', to: { name: 'audit'     }, icon: ShieldCheckIcon },
  { name: 'biometric', label: 'Biometric',   to: { name: 'biometric' }, icon: WifiIcon        },
];

const adminOnlyItems = [
  { name: 'departments', label: 'Departments', to: { name: 'departments' }, icon: Building2Icon },
  { name: 'backup',      label: 'Backup',      to: { name: 'backup'      }, icon: DatabaseIcon  },
];

const superAdminItems = [
  { name: 'campuses',       label: 'Campuses',       to: { name: 'campuses'       }, icon: MapPinIcon      },
  { name: 'admin-accounts', label: 'Admin Accounts', to: { name: 'admin-accounts' }, icon: ShieldCheckIcon },
  { name: 'access-control', label: 'Access Control', to: { name: 'access-control' }, icon: LockIcon        },
  { name: 'backup',         label: 'Backup',         to: { name: 'backup'         }, icon: DatabaseIcon    },
  { name: 'system-health',  label: 'System Health',  to: { name: 'system-health'  }, icon: HeartPulseIcon  },
  { name: 'settings',       label: 'Settings',       to: { name: 'settings'       }, icon: SettingsIcon    },
];

const isSuperAdmin = computed(() => auth.employee?.role === 'super_admin');
const isAdmin      = computed(() => auth.employee?.role === 'admin' || isSuperAdmin.value);
const isAdminOrHr  = computed(() =>
  ['super_admin', 'admin', 'hr_officer'].includes(auth.employee?.role)
);

const initials = computed(() => {
  const name = auth.employee?.name ?? '';
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
});

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

// ── Pending DTR print requests badge ───────────────────────────────
const pendingPrintRequests = ref(0);
let badgePollTimer = null;

async function fetchPendingPrintRequests() {
  if (!isAdminOrHr.value) return;
  try {
    const res = await axios.get('/api/dtr-print-consents');
    pendingPrintRequests.value = res.data?.summary?.pending ?? 0;
  } catch {
    // silently ignore
  }
}

// ── Real-time punch toast ─────────────────────────────────────────
let echoChannel = null;

function listenForPunches() {
  if (!window.Echo) return;
  echoChannel = window.Echo.channel('attendance')
    .listen('.punched', (e) => {
      const verb = e.punch_type === 'IN' ? 'clocked in' : 'clocked out';
      toast(`${e.name} just ${verb} — ${e.time}`, 'info', 6000);
    });
}

// ── Real-time print-request badge ──────────────────────────────────
// Keeps the sidebar badge accurate the instant a request is submitted
// or reviewed, instead of waiting on the 60s poll below. ReportsView.vue
// listens on this same public channel for its own live queue — named
// callback refs below so unmounting here never rips out that listener
// (stopListening with no callback clears every handler for the event).
let printConsentChannel = null;
const onPrintRequested = (e) => {
  toast(`New print request from ${e.consent.employee?.name ?? 'an employee'}.`, 'info', 6000);
  fetchPendingPrintRequests();
};
const onPrintDecided = () => fetchPendingPrintRequests();

function listenForPrintConsents() {
  if (!window.Echo || !isAdminOrHr.value) return;
  printConsentChannel = window.Echo.channel('print-consents')
    .listen('.requested', onPrintRequested)
    .listen('.decided', onPrintDecided);
}

onMounted(() => {
  fetchPendingPrintRequests();
  badgePollTimer = setInterval(fetchPendingPrintRequests, 60_000);
  listenForPunches();
  listenForPrintConsents();
  refreshFeatureAccess();
});

// Pull the latest feature grants on load, and leave the current page if
// access to it was revoked since sign-in (the router guard only runs on
// navigation, not on a grant that changes underneath an open page).
async function refreshFeatureAccess() {
  await auth.refreshAccess();
  const required = router.currentRoute.value.meta?.requiresFeature;
  if (required && !perms.can(required)) {
    router.replace({ name: 'dashboard' });
  }
}

onBeforeUnmount(() => {
  clearInterval(badgePollTimer);
  echoChannel?.stopListening('.punched');
  window.Echo?.leave('attendance');
  printConsentChannel?.stopListening('.requested', onPrintRequested);
  printConsentChannel?.stopListening('.decided', onPrintDecided);
});
</script>

<style scoped>
/* ── Quick search trigger ─────────────────────────── */
.quick-search-btn {
  width: 100%;
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px;
  border-radius: 9px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.08);
  color: #94A3B8;
  font-size: 12px; font-weight: 500;
  transition: all 0.15s ease;
  cursor: pointer;
}
.quick-search-btn:hover {
  background: rgba(255,255,255,0.09);
  color: #C5D0E8;
  border-color: rgba(255,255,255,0.14);
}
.quick-search-btn span { flex: 1; text-align: left; }
.quick-search-kbd {
  font-family: var(--font-mono); font-size: 9.5px; font-weight: 600;
  color: rgba(157, 173, 216,0.7);
  background: rgba(0,0,0,0.25);
  border: 1px solid rgba(255,255,255,0.1);
  border-bottom-width: 2px;
  border-radius: 4px; padding: 2px 6px;
  flex-shrink: 0;
}

/* ── Version chip — a git-tag-style badge rather than a plain label ─── */
.version-chip {
  font-family: var(--font-mono);
  font-size: 8.5px; font-weight: 700; letter-spacing: 0.02em;
  color: #E8C040;
  background: rgba(201, 162, 39,0.14);
  border: 1px solid rgba(201, 162, 39,0.3);
  border-radius: 4px;
  padding: 1px 5px;
  line-height: 1.4;
}

/* ── Section labels ──────────────────────────────── */
.nav-section-label {
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: rgba(157, 173, 216,0.38);
  padding: 0 12px 6px;
  margin-top: 4px;
}

/* ── Nav link base ───────────────────────────────── */
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px 8px 8px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  transition: all 0.18s ease;
  position: relative;
  margin-bottom: 2px;
  text-decoration: none;
}

/* ── Icon wrapper ────────────────────────────────── */
.nav-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.18s ease;
}
.nav-icon-idle {
  background: rgba(255,255,255,0.06);
  color: #94A3B8;
  border: 1px solid rgba(255,255,255,0.07);
}
.nav-icon-active {
  background: linear-gradient(135deg, #C9A227, #B8911F);
  color: #fff;
  border: none;
  box-shadow: 0 2px 8px rgba(201, 162, 39,0.35);
}
.nav-icon-gold {
  background: rgba(201, 162, 39,0.12);
  color: #C9A227;
  border: 1px solid rgba(201, 162, 39,0.2);
}

/* ── Active state ────────────────────────────────── */
.nav-active {
  background: linear-gradient(90deg, rgba(201, 162, 39,0.15) 0%, rgba(201, 162, 39,0.04) 100%);
  color: #F0D060;
  font-weight: 700;
  animation: navActiveIn 0.25s ease;
}
@keyframes navActiveIn {
  from { opacity: 0; transform: translateX(-6px); }
  to   { opacity: 1; transform: none; }
}

/* ── Active left pip ─────────────────────────────── */
.nav-pip {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 18px;
  border-radius: 0 3px 3px 0;
  background: linear-gradient(180deg, #E8C040, #C9A227);
  box-shadow: 0 0 8px rgba(201, 162, 39,0.6);
  animation: pipIn 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes pipIn {
  from { transform: translateY(-50%) scaleY(0); opacity: 0; }
  to   { transform: translateY(-50%) scaleY(1); opacity: 1; }
}

/* ── Idle / hover state ──────────────────────────── */
.nav-idle {
  color: #8A9DBF;
}
.nav-idle:hover {
  color: #D8E0F0;
  background: rgba(255,255,255,0.06);
}
.nav-idle:hover .nav-icon-idle {
  background: rgba(255,255,255,0.1);
  color: #C5D0E8;
  border-color: rgba(255,255,255,0.12);
}
.nav-idle:hover .nav-icon-gold {
  background: rgba(201, 162, 39,0.2);
  color: #E8C040;
}

/* ── Nav label ───────────────────────────────────── */
.nav-label {
  flex: 1;
  line-height: 1.2;
}

/* ── Notification badge ──────────────────────────── */
.nav-badge {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: #EF4444;
  color: #fff;
  font-size: 9.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  line-height: 1;
  box-shadow: 0 1px 4px rgba(239,68,68,0.4);
  animation: badge-pop 0.2s ease;
}
@keyframes badge-pop {
  from { transform: scale(0.6); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

/* ── User card buttons ───────────────────────────── */
.logout-btn,
.changepwd-btn { color: rgba(157, 173, 216,0.7); background: transparent; }
.logout-btn:hover,
.changepwd-btn:hover { color: #C9A227; background: rgba(255,255,255,0.08); }
</style>

<style>
.page-enter-active {
  transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.page-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
  /* Enter and leave run at the same time (see the RouterView note), so take
     the outgoing page out of the flow — otherwise both pages stack for a
     moment and the new one pops in below the old, then jumps up. Offset
     matches <main>'s pt-14 under the mobile top bar. */
  position: absolute;
  top: 3.5rem;
  left: 0;
  right: 0;
  pointer-events: none;
}
@media (min-width: 1024px) {
  .page-leave-active { top: 0; }
}
.page-enter-from { opacity: 0; transform: translateY(10px) scale(0.995); }
.page-leave-to   { opacity: 0; transform: translateY(-5px); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from,
.fade-leave-to     { opacity: 0; }
</style>
