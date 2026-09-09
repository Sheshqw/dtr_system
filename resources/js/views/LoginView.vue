<template>
  <div
    class="min-h-screen flex items-center justify-center relative overflow-hidden px-5 py-10"
    style="background: linear-gradient(150deg, #0550A8 0%, #082E88 28%, #081A68 58%, #080E80 100%);"
  >

    <!-- ── Ambient orbs ────────────────────────────────────────────────────── -->
    <div class="absolute inset-0 pointer-events-none">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>
      <div class="orb orb-4"></div>
    </div>

    <!-- Dot-grid texture -->
    <div
      class="absolute inset-0 pointer-events-none"
      style="background-image: radial-gradient(rgba(255,255,255,0.026) 1px, transparent 1px); background-size: 24px 24px;"
    ></div>

    <!-- Decorative hexagon grid (bottom-left) -->
    <div class="absolute bottom-10 left-8 pointer-events-none opacity-[0.06]">
      <svg width="200" height="200" viewBox="0 0 220 180" fill="none">
        <g stroke="white" stroke-width="1">
          <polygon points="55,10 75,10 85,27 75,44 55,44 45,27"/>
          <polygon points="105,10 125,10 135,27 125,44 105,44 95,27"/>
          <polygon points="155,10 175,10 185,27 175,44 155,44 145,27"/>
          <polygon points="30,44 50,44 60,61 50,78 30,78 20,61"/>
          <polygon points="80,44 100,44 110,61 100,78 80,78 70,61"/>
          <polygon points="130,44 150,44 160,61 150,78 130,78 120,61"/>
          <polygon points="180,44 200,44 210,61 200,78 180,78 170,61"/>
          <polygon points="55,78 75,78 85,95 75,112 55,112 45,95"/>
          <polygon points="105,78 125,78 135,95 125,112 105,112 95,95"/>
          <polygon points="155,78 175,78 185,95 175,112 155,112 145,95"/>
        </g>
      </svg>
    </div>

    <!-- Decorative rings (top-right) -->
    <div class="absolute -top-24 -right-24 pointer-events-none">
      <div class="ring ring-1"></div>
      <div class="ring ring-2"></div>
      <div class="ring ring-3"></div>
    </div>

    <!-- Coastal wave layers (bottom) -->
    <div class="absolute bottom-0 left-0 right-0 pointer-events-none">
      <svg viewBox="0 0 1440 130" preserveAspectRatio="none" class="w-full block">
        <path d="M0,80 C200,120 400,40 600,80 C800,120 1000,40 1200,70 C1300,85 1380,60 1440,75 L1440,130 L0,130 Z"
              fill="rgba(255,255,255,0.045)"/>
        <path d="M0,100 C300,60 600,130 900,90 C1100,62 1280,110 1440,80 L1440,130 L0,130 Z"
              fill="rgba(255,255,255,0.03)"/>
        <path d="M0,115 C240,90 480,128 720,110 C960,92 1200,125 1440,108 L1440,130 L0,130 Z"
              fill="rgba(0,87,168,0.20)"/>
      </svg>
    </div>

    <!-- Gold shimmer line -->
    <div
      class="absolute left-0 right-0 h-px pointer-events-none"
      style="top: 38%; background: linear-gradient(90deg, transparent 5%, rgba(201,162,39,0.14) 35%, rgba(201,162,39,0.26) 50%, rgba(201,162,39,0.14) 65%, transparent 95%);"
    ></div>

    <!-- ── Centered card wrapper ───────────────────────────────────────────── -->
    <div class="relative w-full max-w-[460px] card-float">

      <!-- Logo block -->
      <div class="flex flex-col items-center mb-6 logo-enter">
        <div class="relative mb-3">
          <div class="absolute inset-0 rounded-full logo-glow"></div>
          <img
            :src="settings.logoUrl"
            :alt="settings.systemName"
            class="relative w-[80px] h-[80px] rounded-full object-cover"
            style="box-shadow: 0 0 0 2.5px rgba(201,162,39,0.70), 0 0 0 7px rgba(201,162,39,0.12), 0 12px 44px rgba(0,0,0,0.55);"
          />
          <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-[#081A68] bg-emerald-400 pulse-dot"></span>
        </div>

        <h1 class="text-white text-[22px] font-extrabold tracking-[0.20em] uppercase leading-tight">{{ settings.systemName }}</h1>
        <p class="text-[11px] font-bold tracking-[0.24em] uppercase mt-0.5 leading-tight" style="color: #C9A227;">
          Cantilan Campus
        </p>
        <div class="flex items-center gap-1.5 mt-2.5 px-3.5 py-1.5 rounded-full"
             style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.11);">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
          <span class="text-[10px] font-semibold tracking-wider" style="color: rgba(186,210,255,0.60);">
            Daily Time Record System · v1.0
          </span>
        </div>
      </div>

      <!-- ── Glass card ──────────────────────────────────────────────────── -->
      <div class="card-enter card-glass rounded-3xl overflow-hidden">

        <!-- Top accent bar: sky-blue → navy → gold -->
        <div class="h-[3px] w-full" style="background: linear-gradient(90deg, #0284C7 0%, #0E1EC0 35%, #C9A227 70%, #F0C040 100%);"></div>

        <div class="px-8 pt-7 pb-8">

          <!-- Card header -->
          <div class="mb-7 text-center">
            <h2 class="text-[21px] font-extrabold leading-tight text-white">Welcome back</h2>
            <p class="text-[13px] mt-1" style="color: rgba(168,184,232,0.58);">Sign in to your account to continue</p>
          </div>

          <form @submit.prevent="handleLogin" class="space-y-4" novalidate>

            <!-- Email -->
            <div>
              <label class="block text-[10px] font-bold tracking-[0.18em] uppercase mb-2"
                     style="color: rgba(168,184,232,0.52);">
                Email Address
              </label>
              <div class="relative">
                <MailIcon
                  class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                  :style="{ color: focused === 'email' ? '#C9A227' : 'rgba(168,184,232,0.32)' }"
                />
                <input
                  v-model="form.email"
                  type="email"
                  required
                  autocomplete="email"
                  placeholder="you@nemsu-cantilan.edu.ph"
                  class="input-field w-full pl-10 pr-4 py-3 text-sm rounded-xl outline-none transition-all duration-200"
                  :style="inputStyle('email')"
                  @focus="focused = 'email'"
                  @blur="focused = null"
                />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-[10px] font-bold tracking-[0.18em] uppercase mb-2"
                     style="color: rgba(168,184,232,0.52);">
                Password
              </label>
              <div class="relative">
                <LockIcon
                  class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                  :style="{ color: focused === 'pwd' ? '#C9A227' : 'rgba(168,184,232,0.32)' }"
                />
                <input
                  v-model="form.password"
                  :type="showPwd ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  class="input-field w-full pl-10 pr-11 py-3 text-sm rounded-xl outline-none transition-all duration-200"
                  :style="inputStyle('pwd')"
                  @focus="focused = 'pwd'"
                  @blur="focused = null"
                />
                <button
                  type="button"
                  @click="showPwd = !showPwd"
                  tabindex="-1"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 p-1 rounded-md transition-all duration-150"
                  :style="{ color: showPwd ? '#C9A227' : 'rgba(168,184,232,0.38)' }"
                >
                  <EyeOffIcon v-if="showPwd" class="w-4 h-4" />
                  <EyeIcon    v-else          class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Remember me -->
            <div class="flex items-center gap-2.5 pt-0.5">
              <button
                type="button"
                @click="rememberMe = !rememberMe"
                class="remember-toggle flex-shrink-0"
                :class="rememberMe ? 'remember-on' : 'remember-off'"
              >
                <CheckIcon v-if="rememberMe" class="w-3 h-3" />
              </button>
              <span
                class="text-[12px] font-medium select-none cursor-pointer"
                style="color: rgba(168,184,232,0.60);"
                @click="rememberMe = !rememberMe"
              >
                Remember me
              </span>
            </div>

            <!-- Error -->
            <Transition name="err">
              <div
                v-if="error"
                class="flex items-start gap-2.5 px-4 py-3 rounded-xl text-sm"
                style="background: rgba(220,38,38,0.12); border: 1px solid rgba(220,38,38,0.28); color: #FCA5A5;"
              >
                <AlertCircleIcon class="w-4 h-4 flex-shrink-0 mt-0.5" />
                <span>{{ error }}</span>
              </div>
            </Transition>

            <!-- Submit -->
            <button
              type="submit"
              :disabled="loading || !form.email || !form.password"
              class="relative w-full py-3.5 text-sm font-bold rounded-xl overflow-hidden transition-all duration-200 disabled:opacity-45 disabled:cursor-not-allowed group mt-1"
              :style="btnStyle"
            >
              <div
                class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"
                style="background: linear-gradient(135deg, rgba(255,255,255,0.20) 0%, transparent 60%);"
              ></div>
              <span v-if="loading" class="relative flex items-center justify-center gap-2.5 text-[#081A68]">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                Authenticating…
              </span>
              <span v-else class="relative flex items-center justify-center gap-2 text-[#081A68]">
                Sign In
                <ArrowRightIcon class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" />
              </span>
            </button>

          </form>

          <!-- Card footer -->
          <div class="mt-6 pt-5" style="border-top: 1px solid rgba(255,255,255,0.07);">
            <p class="text-center text-[10.5px]" style="color: rgba(168,184,232,0.28);">
              Authorized personnel only &nbsp;&middot;&nbsp;
              <span style="color: rgba(201,162,39,0.52);">© {{ currentYear }} {{ settings.systemName }} Cantilan Campus</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Below-card note -->
      <p class="text-center text-[10px] mt-4" style="color: rgba(186,210,255,0.20);">
        Republic of the Philippines &middot; State University &middot; Cantilan, Surigao del Sur
      </p>
      <p class="text-center text-[9.5px] italic mt-0.5" style="color: rgba(186,210,255,0.14);">
        "Carving a name in the map of higher education"
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import {
  Mail          as MailIcon,
  Lock          as LockIcon,
  Eye           as EyeIcon,
  EyeOff        as EyeOffIcon,
  AlertCircle   as AlertCircleIcon,
  ArrowRight    as ArrowRightIcon,
  Check         as CheckIcon,
} from 'lucide-vue-next';

const auth     = useAuthStore();
const router   = useRouter();
const route    = useRoute();
const settings = useSettingsStore();

const form       = reactive({ email: '', password: '' });
const loading    = ref(false);
const error      = ref('');
const showPwd    = ref(false);
const focused    = ref(null);
const rememberMe = ref(true);

const currentYear = new Date().getFullYear();

function inputStyle(field) {
  const active = focused.value === field;
  return {
    background: active ? 'rgba(255,255,255,0.10)' : 'rgba(255,255,255,0.055)',
    border:     `1.5px solid ${active ? 'rgba(201,162,39,0.65)' : 'rgba(255,255,255,0.10)'}`,
    boxShadow:  active ? '0 0 0 3px rgba(201,162,39,0.12)' : 'none',
    color:      '#FFFFFF',
  };
}

const btnStyle = computed(() => ({
  background:     'linear-gradient(135deg, #C9A227, #E8B830, #C9A227)',
  backgroundSize: '200% 100%',
  boxShadow:      loading.value ? 'none' : '0 4px 28px rgba(201,162,39,0.45), 0 2px 8px rgba(0,0,0,0.25)',
}));

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    await auth.login(form.email, form.password, rememberMe.value);
    const redirect = route.query.redirect;
    router.push(redirect ? String(redirect) : { name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Login failed. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* ── Ambient orbs ─────────────────────────────────────────────────────────── */
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  will-change: transform;
}
.orb-1 {
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(0,150,214,0.50), transparent 70%);
  top: -220px; left: -200px;
  opacity: 0.32;
  animation: drift 32s ease-in-out infinite;
}
.orb-2 {
  width: 460px; height: 460px;
  background: radial-gradient(circle, rgba(201,162,39,0.42), transparent 70%);
  top: 30%; right: -180px;
  opacity: 0.20;
  animation: drift 26s ease-in-out infinite reverse;
  animation-delay: -9s;
}
.orb-3 {
  width: 520px; height: 520px;
  background: radial-gradient(circle, rgba(7,27,106,0.70), transparent 70%);
  bottom: -160px; left: 10%;
  opacity: 0.40;
  animation: drift 38s ease-in-out infinite;
  animation-delay: -18s;
}
.orb-4 {
  width: 320px; height: 320px;
  background: radial-gradient(circle, rgba(79,70,229,0.38), transparent 70%);
  bottom: 15%; right: 8%;
  opacity: 0.18;
  animation: drift 22s ease-in-out infinite reverse;
  animation-delay: -5s;
}

@keyframes drift {
  0%,  100% { transform: translate(0px,   0px)   scale(1);    }
  33%        { transform: translate(28px, -38px)  scale(1.06); }
  66%        { transform: translate(-20px, 26px)  scale(0.94); }
}

/* ── Decorative rings ─────────────────────────────────────────────────────── */
.ring {
  position: absolute;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,0.06);
}
.ring-1 { width: 260px; height: 260px; top: 0;    left: 0;    }
.ring-2 { width: 400px; height: 400px; top: -70px; left: -70px; border-color: rgba(201,162,39,0.07); }
.ring-3 { width: 540px; height: 540px; top: -140px; left: -140px; border-color: rgba(255,255,255,0.03); }

/* ── Card float ───────────────────────────────────────────────────────────── */
.card-float {
  animation: cardFloat 7s ease-in-out infinite;
  will-change: transform;
}
@keyframes cardFloat {
  0%,  100% { transform: translateY(0px);  }
  50%        { transform: translateY(-8px); }
}

/* ── Glass card ───────────────────────────────────────────────────────────── */
.card-glass {
  background: rgba(5, 12, 48, 0.68);
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  border: 1px solid rgba(255, 255, 255, 0.09);
  box-shadow:
    0 48px 110px rgba(0, 0, 0, 0.60),
    0 14px 44px  rgba(0, 0, 0, 0.32),
    0  0    0 1px rgba(201, 162, 39, 0.06),
    inset 0  1px 0 rgba(255, 255, 255, 0.07),
    inset 0 -1px 0 rgba(255, 255, 255, 0.03);
}

/* ── Logo glow ────────────────────────────────────────────────────────────── */
.logo-glow {
  background: radial-gradient(circle, rgba(201,162,39,0.40), transparent 65%);
  filter: blur(14px);
  animation: glowPulse 4s ease-in-out infinite;
  transform: scale(1.5);
}
@keyframes glowPulse {
  0%, 100% { opacity: 0.6; transform: scale(1.5); }
  50%       { opacity: 1.0; transform: scale(1.75); }
}

/* ── Pulse dot ────────────────────────────────────────────────────────────── */
.pulse-dot {
  box-shadow: 0 0 0 0 rgba(52,211,153,0.7);
  animation: dotPulse 2.5s ease-out infinite;
}
@keyframes dotPulse {
  0%   { box-shadow: 0 0 0 0   rgba(52,211,153,0.7); }
  70%  { box-shadow: 0 0 0 6px rgba(52,211,153,0);   }
  100% { box-shadow: 0 0 0 0   rgba(52,211,153,0);   }
}

/* ── Entrance animations ──────────────────────────────────────────────────── */
.logo-enter {
  animation: logoIn 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.05s both;
}
@keyframes logoIn {
  from { opacity: 0; transform: translateY(-20px) scale(0.94); }
  to   { opacity: 1; transform: translateY(0)      scale(1);   }
}

.card-enter {
  animation: cardIn 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.18s both;
}
@keyframes cardIn {
  from { opacity: 0; transform: translateY(26px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0)    scale(1);    }
}

/* ── Remember me toggle ───────────────────────────────────────────────────── */
.remember-toggle {
  width: 18px; height: 18px;
  border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.18s, border-color 0.18s, box-shadow 0.18s;
  cursor: pointer;
}
.remember-off {
  background: rgba(255,255,255,0.06);
  border: 1.5px solid rgba(255,255,255,0.18);
}
.remember-off:hover {
  border-color: rgba(201,162,39,0.50);
  background: rgba(201,162,39,0.08);
}
.remember-on {
  background: #C9A227;
  border: 1.5px solid #C9A227;
  color: #081A68;
  box-shadow: 0 0 0 3px rgba(201,162,39,0.18);
}

/* ── Input ────────────────────────────────────────────────────────────────── */
.input-field::placeholder {
  color: rgba(168, 184, 232, 0.26);
}

/* ── Error transition ─────────────────────────────────────────────────────── */
.err-enter-active { animation: errIn  0.28s cubic-bezier(0.34, 1.56, 0.64, 1); }
.err-leave-active { animation: errOut 0.18s ease-in; }
@keyframes errIn  { from { opacity: 0; transform: translateY(-6px) scale(0.97); } to { opacity: 1; transform: none; } }
@keyframes errOut { to   { opacity: 0; transform: translateY(-4px) scale(0.97); }                                     }
</style>
