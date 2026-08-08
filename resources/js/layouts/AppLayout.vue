<template>
  <div class="flex h-screen overflow-hidden" style="background: #F4F6FB;">

    <!-- ── Mobile top bar (hidden on desktop) ───────────────────── -->
    <header
      class="lg:hidden fixed top-0 left-0 right-0 z-30 flex items-center gap-3 px-4 h-14"
      style="background: linear-gradient(135deg, #080E80, #1020C4); border-bottom: 1px solid rgba(255,255,255,0.08);"
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
        <img :src="logo" alt="NEMSU" class="w-7 h-7 rounded-full object-cover flex-shrink-0"
          style="box-shadow: 0 0 0 1.5px rgba(201,162,39,0.6);" />
        <div class="leading-tight">
          <p class="text-white text-xs font-bold tracking-wide">NEMSU</p>
          <p class="text-[9px] font-semibold tracking-widest uppercase" style="color: #C9A227;">UDTRMS</p>
        </div>
      </div>

      <!-- Mobile user avatar -->
      <div
        class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold"
        style="background: linear-gradient(135deg, #C9A227, #9A7A1A);"
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
      style="background: linear-gradient(180deg, #080E80 0%, #0A16A0 45%, #1020C4 100%); box-shadow: 4px 0 28px rgba(8,14,128,0.28), 1px 0 0 rgba(8,14,128,0.06);"
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
              :src="logo"
              alt="NEMSU"
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
            <p class="text-white text-sm font-bold leading-tight tracking-wide">NEMSU</p>
            <p
              class="text-[11px] font-semibold tracking-widest uppercase leading-tight transition-colors duration-300"
              :style="{ color: campus.heroBand }"
            >{{ campusName }}</p>
            <p class="text-[9px] leading-tight mt-0.5" style="color: rgba(157,173,216,0.6);">UDTRMS v1.0</p>
          </div>
        </div>

        <!-- Close button (mobile only) -->
        <button
          @click="sidebarOpen = false"
          class="lg:hidden p-1.5 rounded-lg transition-colors flex-shrink-0"
          style="color: rgba(157,173,216,0.7);"
          aria-label="Close navigation"
        >
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Nav links -->
      <nav class="relative flex-1 px-3 py-4 overflow-y-auto" style="space-y: 0;">

        <!-- Section label: Main -->
        <p class="nav-section-label">Main</p>

        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="nav-link group"
          :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
          @click="sidebarOpen = false"
        >
          <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-idle'">
            <component :is="item.icon" class="w-3.5 h-3.5" />
          </span>
          <span class="nav-label">{{ item.label }}</span>
          <span
            v-if="item.name === 'corrections' && pendingCorrections > 0"
            class="nav-badge"
          >{{ pendingCorrections > 99 ? '99+' : pendingCorrections }}</span>
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
              <component :is="item.icon" class="w-3.5 h-3.5" />
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
                <component :is="item.icon" class="w-3.5 h-3.5" />
              </span>
              <span class="nav-label">{{ item.label }}</span>
              <span v-if="$route.name === item.name" class="nav-pip" />
            </RouterLink>
          </template>

          <template v-if="isSuperAdmin">
            <p class="nav-section-label" style="margin-top: 18px; color: rgba(201,162,39,0.55);">Super Admin</p>
            <RouterLink
              v-for="item in superAdminItems"
              :key="item.name"
              :to="item.to"
              class="nav-link group"
              :class="$route.name === item.name ? 'nav-active' : 'nav-idle'"
              @click="sidebarOpen = false"
            >
              <span class="nav-icon-wrap" :class="$route.name === item.name ? 'nav-icon-active' : 'nav-icon-gold'">
                <component :is="item.icon" class="w-3.5 h-3.5" />
              </span>
              <span class="nav-label">{{ item.label }}</span>
              <span v-if="$route.name === item.name" class="nav-pip" />
            </RouterLink>
          </template>
        </template>
      </nav>

      <!-- Gold divider -->
      <div class="relative mx-4 h-px" style="background: linear-gradient(90deg, transparent, rgba(201,162,39,0.35), transparent);"></div>

      <!-- Theme mode selector -->
      <div class="relative px-4 py-3">
        <p class="theme-seg-label-header">Appearance</p>
        <div class="theme-seg" role="group" aria-label="Theme mode">
          <button
            v-for="opt in themeOptions"
            :key="opt.mode"
            @click="theme.setMode(opt.mode)"
            class="theme-seg-btn"
            :class="[
              `theme-seg-btn--${opt.mode}`,
              theme.mode === opt.mode ? `theme-seg-btn--active theme-seg-btn--active-${opt.mode}` : ''
            ]"
            :aria-pressed="theme.mode === opt.mode"
            :title="opt.label"
          >
            <component :is="opt.icon" class="w-3.5 h-3.5" />
            <span class="theme-seg-text">{{ opt.label }}</span>
          </button>
        </div>
      </div>

      <!-- User card -->
      <div class="relative px-4 pb-4">
        <div
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl"
          style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 shadow-md"
            style="background: linear-gradient(135deg, #C9A227, #9A7A1A);"
          >
            <span class="text-white text-xs font-bold">{{ initials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-xs font-semibold truncate leading-tight">{{ auth.employee?.name }}</p>
            <p class="text-[10px] truncate capitalize mt-0.5 leading-tight" style="color: #9DADD8;">
              {{ roleLabel(auth.employee?.role) }}
            </p>
          </div>
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
    <main class="flex-1 overflow-y-auto relative pt-14 lg:pt-0">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
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
            <KeyIcon class="w-5 h-5 text-[#0E1EC0]" />
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-[#0F172A]">Change Password</h2>
            <p class="text-xs text-[#64748B]">Update your account password</p>
          </div>
          <button @click="closePwdModal" class="text-[#94A3B8] hover:text-[#0F172A] transition-colors" aria-label="Close">
            <XIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="submitPwd" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Current Password</label>
            <input v-model="pwdForm.current_password" type="password" required placeholder="Enter current password"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0E1EC0] bg-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">New Password</label>
            <input v-model="pwdForm.password" type="password" required minlength="8" placeholder="At least 8 characters"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0E1EC0] bg-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Confirm New Password</label>
            <input v-model="pwdForm.password_confirmation" type="password" required placeholder="Repeat new password"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0E1EC0] bg-white" />
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
              class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#0E1EC0] hover:bg-[#1228CC] text-white text-sm font-medium rounded-lg disabled:opacity-60 transition-colors">
              <Loader2Icon v-if="pwdSaving" class="w-4 h-4 animate-spin" />
              Save Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <ToastContainer />
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
  Sun            as SunIcon,
  Moon           as MoonIcon,
  Monitor        as MonitorIcon,
  ClipboardEdit  as ClipboardEditIcon,
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { useToast } from '@/composables/useToast';
import { useCampusIdentity } from '@/composables/useCampusIdentity';
import ToastContainer from '@/components/ToastContainer.vue';

const auth   = useAuthStore();
const theme  = useThemeStore();

const themeOptions = [
  { mode: 'light',  label: 'Light',  icon: SunIcon     },
  { mode: 'system', label: 'System', icon: MonitorIcon  },
  { mode: 'dark',   label: 'Dark',   icon: MoonIcon     },
];
const router = useRouter();
const logo   = '/nemsu.png';
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
const navItems = [
  { name: 'dashboard',   label: 'Dashboard',    to: { name: 'dashboard'   }, icon: LayoutDashboardIcon },
  { name: 'attendance',  label: 'Attendance',   to: { name: 'attendance'  }, icon: ClockIcon           },
  { name: 'employees',   label: 'Employees',    to: { name: 'employees'   }, icon: UsersIcon           },
  { name: 'tracking',    label: 'Tracking',     to: { name: 'tracking'    }, icon: BarChart2Icon       },
  { name: 'reports',     label: 'Reports',      to: { name: 'reports'     }, icon: FileTextIcon        },
  { name: 'corrections', label: 'Corrections',  to: { name: 'corrections' }, icon: ClipboardEditIcon  },
];

const hrItems = [
  { name: 'holidays',  label: 'Holidays',    to: { name: 'holidays'  }, icon: CalendarIcon    },
  { name: 'audit',     label: 'Audit Trail', to: { name: 'audit'     }, icon: ShieldCheckIcon },
  { name: 'biometric', label: 'Biometric',   to: { name: 'biometric' }, icon: WifiIcon        },
];

const adminOnlyItems = [
  { name: 'shifts',      label: 'Shifts',      to: { name: 'shifts'      }, icon: TimerIcon     },
  { name: 'departments', label: 'Departments', to: { name: 'departments' }, icon: Building2Icon },
  { name: 'backup',      label: 'Backup',      to: { name: 'backup'      }, icon: DatabaseIcon  },
];

const superAdminItems = [
  { name: 'campuses',       label: 'Campuses',       to: { name: 'campuses'       }, icon: MapPinIcon      },
  { name: 'admin-accounts', label: 'Admin Accounts', to: { name: 'admin-accounts' }, icon: ShieldCheckIcon },
  { name: 'backup',         label: 'Backup',         to: { name: 'backup'         }, icon: DatabaseIcon    },
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

// ── Pending corrections badge ─────────────────────────────────────
const pendingCorrections = ref(0);
let correctionPollTimer = null;

async function fetchPendingCorrections() {
  if (!isAdminOrHr.value) return;
  try {
    const res = await axios.get('/api/dtr-corrections');
    pendingCorrections.value = res.data?.summary?.pending ?? 0;
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

onMounted(() => {
  fetchPendingCorrections();
  correctionPollTimer = setInterval(fetchPendingCorrections, 60_000);
  listenForPunches();
});

onBeforeUnmount(() => {
  clearInterval(correctionPollTimer);
  echoChannel?.stopListening('.punched');
  window.Echo?.leave('attendance');
});
</script>

<style scoped>
/* ── Section labels ──────────────────────────────── */
.nav-section-label {
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: rgba(157,173,216,0.38);
  padding: 0 12px 6px;
  margin-top: 4px;
}

/* ── Nav link base ───────────────────────────────── */
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 10px 6px 8px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.18s ease;
  position: relative;
  margin-bottom: 2px;
  text-decoration: none;
}

/* ── Icon wrapper ────────────────────────────────── */
.nav-icon-wrap {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.18s ease;
}
.nav-icon-idle {
  background: rgba(255,255,255,0.06);
  color: #7B8FBF;
  border: 1px solid rgba(255,255,255,0.07);
}
.nav-icon-active {
  background: linear-gradient(135deg, #C9A227, #A07A18);
  color: #fff;
  border: none;
  box-shadow: 0 2px 8px rgba(201,162,39,0.35);
}
.nav-icon-gold {
  background: rgba(201,162,39,0.12);
  color: #C9A227;
  border: 1px solid rgba(201,162,39,0.2);
}

/* ── Active state ────────────────────────────────── */
.nav-active {
  background: linear-gradient(90deg, rgba(201,162,39,0.15) 0%, rgba(201,162,39,0.04) 100%);
  color: #F0D060;
  font-weight: 600;
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
  box-shadow: 0 0 8px rgba(201,162,39,0.6);
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
  background: rgba(201,162,39,0.2);
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

/* ── Three-mode theme selector ───────────────────── */
.theme-seg-label-header {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(157,173,216,0.4);
  margin: 0 0 8px 2px;
}
.theme-seg {
  display: flex;
  gap: 3px;
  background: rgba(0,0,0,0.28);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 13px;
  padding: 4px;
}
.theme-seg-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 8px 6px;
  border-radius: 9px;
  border: none;
  background: transparent;
  color: rgba(157,173,216,0.45);
  cursor: pointer;
  transition: color 0.18s ease, background 0.18s ease;
}
.theme-seg-btn:hover {
  color: rgba(255,255,255,0.8);
  background: rgba(255,255,255,0.07);
}
.theme-seg-text {
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.04em;
  line-height: 1;
}
/* Active states — each mode has its own clearly distinct accent */
.theme-seg-btn--active-light {
  background: rgba(201,162,39,0.20);
  color: #D4A930;
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.theme-seg-btn--active-system {
  background: rgba(226,232,240,0.12);
  color: #E2E8F0;
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.theme-seg-btn--active-dark {
  background: rgba(129,140,248,0.18);
  color: #818CF8;
  box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.theme-seg-btn--active:hover {
  filter: brightness(1.12);
}

/* ── User card buttons ───────────────────────────── */
.logout-btn,
.changepwd-btn { color: rgba(157,173,216,0.7); background: transparent; }
.logout-btn:hover,
.changepwd-btn:hover { color: #C9A227; background: rgba(255,255,255,0.08); }
</style>

<style>
.page-enter-active {
  transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.page-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}
.page-enter-from { opacity: 0; transform: translateY(10px) scale(0.995); }
.page-leave-to   { opacity: 0; transform: translateY(-5px); }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from,
.fade-leave-to     { opacity: 0; }
</style>
