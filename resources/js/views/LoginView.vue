<template>
  <div class="login-page min-h-screen flex flex-col relative overflow-hidden">

    <!-- ── Background ─────────────────────────────────────────────────────
         Modelled on the university website's hero: a campus photo under a
         navy wash when one is uploaded (System Settings), otherwise an
         illustrated campus at sunrise over the Pacific coast of Surigao
         del Sur. -->
    <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
      <img
        v-if="settings.loginBackgroundUrl"
        :src="settings.loginBackgroundUrl"
        alt=""
        class="absolute inset-0 w-full h-full object-cover"
      />
      <svg
        v-else
        class="absolute inset-0 w-full h-full"
        viewBox="0 0 1440 900"
        preserveAspectRatio="xMidYMax slice"
      >
        <defs>
          <linearGradient id="lg-sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0"    stop-color="#0E1A44" />
            <stop offset="0.55" stop-color="#1B2A5E" />
            <stop offset="0.82" stop-color="#2E4A8C" />
            <stop offset="1"    stop-color="#3D5CA3" />
          </linearGradient>
          <radialGradient id="lg-sun" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0"   stop-color="#E8C040" stop-opacity="0.55" />
            <stop offset="0.4" stop-color="#C9A227" stop-opacity="0.20" />
            <stop offset="1"   stop-color="#C9A227" stop-opacity="0" />
          </radialGradient>
        </defs>

        <rect width="1440" height="900" fill="url(#lg-sky)" />

        <!-- Sunrise glow + the seal-like rings around it — between the hero
             text and the sign-in card, so neither covers it -->
        <circle cx="680" cy="720" r="420" fill="url(#lg-sun)" />
        <g class="scene-wide">
          <circle cx="680" cy="720" r="130" fill="none" stroke="#E8C040" stroke-opacity="0.16" stroke-width="1.5" />
          <circle cx="680" cy="720" r="210" fill="none" stroke="#E8C040" stroke-opacity="0.09" stroke-width="1" />
          <circle cx="680" cy="720" r="300" fill="none" stroke="#FFFFFF" stroke-opacity="0.05" stroke-width="1" />
        </g>

        <!-- Far ridge -->
        <path d="M0,640 C140,600 230,560 360,585 C470,606 540,548 660,560 C780,572 860,620 980,612 C1110,603 1210,560 1320,575 C1380,583 1420,598 1440,604 L1440,900 L0,900 Z"
              fill="#2A3F7E" fill-opacity="0.55" />
        <!-- Near hills -->
        <path d="M0,700 C180,660 320,690 470,672 C600,657 700,690 840,680 C1010,668 1160,636 1300,650 C1370,657 1410,668 1440,672 L1440,900 L0,900 Z"
              fill="#16224F" fill-opacity="0.85" />

        <!-- Campus building (drawn around x=1000, placed at x=680) -->
        <g class="scene-wide" transform="translate(680 752) scale(0.72) translate(-1000 -702)">
        <g fill="#0D1640" fill-opacity="0.92">
          <rect x="984" y="520" width="32" height="70" />
          <polygon points="978,520 1000,490 1022,520" />
          <rect x="999" y="452" width="2" height="40" />
          <polygon points="1001,453 1026,460 1001,467" fill="#C9A227" fill-opacity="0.75" />
          <polygon points="842,604 1000,560 1158,604" />
          <rect x="836" y="604" width="328" height="12" />
          <rect x="852" y="616" width="296" height="70" />
          <rect x="826" y="686" width="348" height="8" />
          <rect x="816" y="694" width="368" height="8" />
        </g>
        <g fill="#E8C040" fill-opacity="0.22">
          <rect x="872"  y="626" width="12" height="52" />
          <rect x="912"  y="626" width="12" height="52" />
          <rect x="952"  y="626" width="12" height="52" />
          <rect x="1036" y="626" width="12" height="52" />
          <rect x="1076" y="626" width="12" height="52" />
          <rect x="1116" y="626" width="12" height="52" />
          <rect x="984"  y="636" width="32" height="50" />
        </g>
        </g>

        <!-- Coastline -->
        <path d="M0,760 C200,742 380,770 560,756 C760,740 920,772 1120,758 C1260,748 1360,756 1440,752 L1440,900 L0,900 Z"
              fill="#0A1336" />
        <path d="M0,790 C240,776 420,800 640,788 C860,776 1040,804 1260,792 C1350,787 1410,790 1440,792 L1440,900 L0,900 Z"
              fill="#0284C7" fill-opacity="0.10" />
        <path d="M0,820 C260,808 480,830 720,820 C960,810 1180,832 1440,822 L1440,900 L0,900 Z"
              fill="#FFFFFF" fill-opacity="0.03" />
      </svg>

      <!-- Navy wash: darkest behind the hero text, lifting toward the card -->
      <div class="login-overlay" :class="{ 'login-overlay--photo': settings.loginBackgroundUrl }"></div>
    </div>

    <!-- ── University header bar ───────────────────────────────────────── -->
    <header class="relative z-10 login-header">
      <div class="max-w-6xl mx-auto px-5 sm:px-8 h-16 flex items-center gap-3">
        <img
          :src="settings.logoUrl"
          :alt="settings.systemName"
          class="w-10 h-10 rounded-full object-cover flex-shrink-0"
          style="box-shadow: 0 0 0 2px rgba(201, 162, 39, 0.75), 0 4px 14px rgba(0, 0, 0, 0.35);"
        />
        <div class="min-w-0 leading-tight">
          <p class="text-[9.5px] font-semibold tracking-[0.22em] uppercase" style="color: rgba(186, 210, 255, 0.70);">Republic of the Philippines</p>
          <p class="login-uni text-white text-[13px] sm:text-[15px] truncate">North Eastern Mindanao State University</p>
        </div>
        <span class="ml-auto hidden sm:inline-flex login-chip">{{ settings.shortName }}</span>
      </div>
      <div class="login-header-rule"></div>
    </header>

    <!-- ── Hero + sign-in ──────────────────────────────────────────────── -->
    <main class="relative z-10 flex-1 flex items-center">
      <div class="w-full max-w-6xl mx-auto px-5 sm:px-8 py-10 lg:py-14 grid lg:grid-cols-[1fr_440px] gap-10 lg:gap-16 items-center">

        <!-- Hero text -->
        <section class="hero-enter text-center lg:text-left">
          <p class="text-[11px] font-bold tracking-[0.26em] uppercase" style="color: #E8C040;">
            Human Resource Management Office
          </p>
          <h1 class="login-title mt-3 text-white">
            {{ settings.tagline }}
          </h1>
          <div class="login-title-rule mx-auto lg:mx-0"></div>
          <p class="hidden sm:block mt-5 max-w-xl mx-auto lg:mx-0 text-[14.5px] leading-relaxed" style="color: rgba(214, 226, 255, 0.78);">
            Biometric attendance, daily time records and the official CSC Form 48 for every
            {{ settings.systemName }} campus — recorded once, accurate to the minute.
          </p>
          <ul class="hidden lg:flex flex-wrap gap-2.5 mt-7">
            <li class="login-feature"><FingerprintIcon class="w-3.5 h-3.5" /> Biometric time-in / time-out</li>
            <li class="login-feature"><FileTextIcon class="w-3.5 h-3.5" /> CSC Form 48 ready to print</li>
            <li class="login-feature"><Building2Icon class="w-3.5 h-3.5" /> All campuses, one system</li>
          </ul>
        </section>

        <!-- ── Glass card ──────────────────────────────────────────────── -->
        <div class="card-enter card-glass rounded-3xl overflow-hidden w-full max-w-[440px] mx-auto">

          <!-- Top accent bar: sky-blue → navy → gold -->
          <div class="h-[3px] w-full" style="background: linear-gradient(90deg, #0284C7 0%, #2E4A8C 35%, #C9A227 70%, #E8C040 100%);"></div>

          <div class="px-8 pt-7 pb-8">

            <!-- Card header -->
            <div class="mb-7 text-center">
              <h2 class="text-[21px] font-bold leading-tight text-white">Welcome back</h2>
              <p class="text-[13px] mt-1" style="color: rgba(168,184,232,0.70);">Sign in to your account to continue</p>
            </div>
            <form @submit.prevent="handleLogin" class="space-y-4" novalidate>

              <!-- Email -->
              <div>
                <label for="login-email" class="block text-[10px] font-bold tracking-[0.18em] uppercase mb-2"
                       style="color: rgba(168,184,232,0.52);">
                  Email Address
                </label>
                <div class="relative">
                  <MailIcon
                    class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                    :style="{ color: focused === 'email' ? '#C9A227' : 'rgba(168,184,232,0.32)' }"
                  />
                  <input
                    id="login-email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="you@nemsu.edu.ph"
                    class="input-field w-full pl-10 pr-4 py-3 text-sm rounded-xl outline-none transition-all duration-200"
                    :style="inputStyle('email')"
                    :aria-invalid="!!error"
                    @focus="focused = 'email'"
                    @blur="focused = null; form.email = form.email.trim()"
                  />
                </div>
              </div>

              <!-- Password -->
              <div>
                <label for="login-password" class="block text-[10px] font-bold tracking-[0.18em] uppercase mb-2"
                       style="color: rgba(168,184,232,0.52);">
                  Password
                </label>
                <div class="relative">
                  <LockIcon
                    class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                    :style="{ color: focused === 'pwd' ? '#C9A227' : 'rgba(168,184,232,0.32)' }"
                  />
                  <input
                    id="login-password"
                    v-model="form.password"
                    :type="showPwd ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="input-field w-full pl-10 pr-11 py-3 text-sm rounded-xl outline-none transition-all duration-200"
                    :style="inputStyle('pwd')"
                    :aria-invalid="!!error"
                    @focus="focused = 'pwd'"
                    @blur="focused = null"
                    @keyup="capsLockOn = $event.getModifierState && $event.getModifierState('CapsLock')"
                  />
                  <button
                    type="button"
                    @click="showPwd = !showPwd"
                    tabindex="-1"
                    class="absolute right-3.5 top-1/2 -translate-y-1/2 p-1 rounded-md transition-all duration-150"
                    :style="{ color: showPwd ? '#C9A227' : 'rgba(168,184,232,0.38)' }"
                    :aria-label="showPwd ? 'Hide password' : 'Show password'"
                    :aria-pressed="showPwd"
                  >
                    <EyeOffIcon v-if="showPwd" class="w-4 h-4" />
                    <EyeIcon    v-else          class="w-4 h-4" />
                  </button>
                </div>
                <!-- Caps Lock warning — the single most common cause of a
                     silently-wrong password on a login form. -->
                <p v-if="capsLockOn" class="flex items-center gap-1.5 mt-1.5 text-[11px] font-medium" style="color: #FCD34D;">
                  <TriangleAlertIcon class="w-3 h-3 flex-shrink-0" />
                  Caps Lock is on
                </p>
              </div>

              <!-- Remember me -->
              <div class="flex items-center gap-2.5 pt-0.5">
                <button
                  type="button"
                  role="checkbox"
                  :aria-checked="rememberMe"
                  aria-label="Remember me"
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

              <!-- Cloudflare Turnstile bot check (only when enabled in System Settings) -->
              <div v-if="settings.turnstileEnabled && settings.turnstileSiteKey">
                <TurnstileWidget
                  ref="turnstileRef"
                  :site-key="settings.turnstileSiteKey"
                  @verified="onTurnstileVerified"
                  @expired="turnstileToken = ''"
                  @error="onTurnstileError"
                />
                <!-- If the widget can't load (usually no internet), signing in
                     stays possible — the server skips the check when it can't
                     reach Cloudflare either. -->
                <p v-if="turnstileFailed" class="flex items-center gap-1.5 mt-1.5 text-[11px] font-medium" style="color: #FCD34D;">
                  <TriangleAlertIcon class="w-3 h-3 flex-shrink-0" />
                  Security check couldn't load. Check your internet connection, or try signing in anyway.
                </p>
              </div>

              <!-- Error -->
              <Transition name="err">
                <div
                  v-if="error"
                  role="alert"
                  aria-live="assertive"
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
                :disabled="loading || !form.email || !form.password || awaitingTurnstile"
                class="relative w-full py-3.5 text-sm font-bold rounded-xl overflow-hidden transition-all duration-200 disabled:opacity-45 disabled:cursor-not-allowed group mt-1"
                :style="btnStyle"
              >
                <div
                  class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"
                  style="background: linear-gradient(135deg, rgba(255,255,255,0.20) 0%, transparent 60%);"
                ></div>
                <span v-if="loading" class="relative flex items-center justify-center gap-2.5 text-[#22305C]">
                  <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                  </svg>
                  Authenticating…
                </span>
                <span v-else class="relative flex items-center justify-center gap-2 text-[#22305C]">
                  Sign In
                  <ArrowRightIcon class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" />
                </span>
              </button>

            </form>

            <!-- Card footer -->
            <div class="mt-6 pt-5" style="border-top: 1px solid rgba(255,255,255,0.07);">
              <p class="text-center text-[10.5px]" style="color: rgba(168,184,232,0.50);">
                Authorized personnel only &nbsp;&middot;&nbsp; Activity is recorded in the audit trail
              </p>
            </div>
          </div>
        </div>

      </div>
    </main>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="relative z-10 login-footer">
      <div class="max-w-6xl mx-auto px-5 sm:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-1 text-[11px] text-center">
        <span>&copy; {{ currentYear }} North Eastern Mindanao State University &middot; Surigao del Sur</span>
        <span>{{ settings.shortName }} &middot; Daily Time Record System v1.0</span>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import {
  Mail          as MailIcon,
  Lock          as LockIcon,
  Eye           as EyeIcon,
  EyeOff        as EyeOffIcon,
  AlertCircle   as AlertCircleIcon,
  ArrowRight    as ArrowRightIcon,
  Check         as CheckIcon,
  TriangleAlert as TriangleAlertIcon,
  Fingerprint   as FingerprintIcon,
  FileText      as FileTextIcon,
  Building2     as Building2Icon,
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
const capsLockOn = ref(false);

const turnstileRef    = ref(null);
const turnstileToken  = ref('');
const turnstileFailed = ref(false);

// Hold the Sign In button until the bot check passes — unless the widget
// couldn't load at all, in which case the server decides (see template note).
const awaitingTurnstile = computed(() =>
  settings.turnstileEnabled && !!settings.turnstileSiteKey && !turnstileToken.value && !turnstileFailed.value
);

function onTurnstileVerified(token) {
  turnstileToken.value  = token;
  turnstileFailed.value = false;
}

function onTurnstileError() {
  turnstileToken.value  = '';
  turnstileFailed.value = true;
}

const currentYear = new Date().getFullYear();

function inputStyle(field) {
  const active = focused.value === field;
  return {
    background: active ? 'rgba(255,255,255,0.10)' : 'rgba(255,255,255,0.055)',
    border:     `1.5px solid ${active ? 'rgba(201, 162, 39,0.65)' : 'rgba(255,255,255,0.10)'}`,
    boxShadow:  active ? '0 0 0 3px rgba(201, 162, 39,0.12)' : 'none',
    color:      '#FFFFFF',
  };
}

const btnStyle = computed(() => ({
  background:     'linear-gradient(135deg, #C9A227, #E8C040, #C9A227)',
  backgroundSize: '200% 100%',
  boxShadow:      loading.value ? 'none' : '0 4px 28px rgba(201, 162, 39,0.45), 0 2px 8px rgba(0,0,0,0.25)',
}));

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  form.email    = form.email.trim();
  try {
    await auth.login(form.email, form.password, rememberMe.value, turnstileToken.value || null);
    const redirect = route.query.redirect;
    router.push(redirect ? String(redirect) : { name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Login failed. Please try again.';
    // A Turnstile token is single-use — the server already spent this one,
    // so get a fresh one before the next attempt.
    turnstileToken.value = '';
    turnstileRef.value?.reset();
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
/* ── Page backdrop ────────────────────────────────────────────────────────── */
.login-page { background: #0E1A44; }

/* Navy wash over the background: darkest on the left behind the hero
   text, lighter toward the sign-in card, and deeper along the bottom so
   the footer stays readable. A photo gets a heavier wash than the
   illustration, since a real campus shot is busier. */
.login-overlay {
  position: absolute; inset: 0;
  background:
    linear-gradient(90deg, rgba(14, 26, 68, 0.70) 0%, rgba(14, 26, 68, 0.30) 55%, rgba(14, 26, 68, 0.10) 100%),
    linear-gradient(0deg, rgba(10, 19, 54, 0.75) 0%, rgba(10, 19, 54, 0) 30%);
}
.login-overlay--photo {
  background:
    linear-gradient(90deg, rgba(14, 26, 68, 0.92) 0%, rgba(27, 42, 94, 0.78) 50%, rgba(27, 42, 94, 0.62) 100%),
    linear-gradient(0deg, rgba(10, 19, 54, 0.85) 0%, rgba(10, 19, 54, 0) 35%);
}
@media (max-width: 1023px) {
  /* The stacked layout puts the card over the middle of the scene, where
     the building would show through the glass. */
  .scene-wide            { display: none; }
  .login-overlay         { background: linear-gradient(180deg, rgba(14, 26, 68, 0.55) 0%, rgba(14, 26, 68, 0.35) 45%, rgba(10, 19, 54, 0.80) 100%); }
  .login-overlay--photo  { background: linear-gradient(180deg, rgba(14, 26, 68, 0.88) 0%, rgba(27, 42, 94, 0.78) 50%, rgba(10, 19, 54, 0.92) 100%); }
}

/* ── University header bar ────────────────────────────────────────────────── */
.login-header {
  background: rgba(10, 19, 54, 0.55);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}
/* Gold-and-blue rule under the header, like the university site's masthead. */
.login-header-rule {
  height: 3px;
  background: linear-gradient(90deg, #C9A227 0%, #E8C040 30%, #2E4A8C 70%, #0284C7 100%);
}
.login-uni {
  font-family: var(--font-serif);
  font-weight: 600;
  letter-spacing: 0.01em;
}
.login-chip {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.18em;
  color: #E8C040;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid rgba(232, 192, 64, 0.40);
  background: rgba(201, 162, 39, 0.10);
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.login-title {
  font-family: var(--font-serif);
  font-weight: 700;
  font-size: clamp(26px, 4.2vw, 44px);
  line-height: 1.12;
  letter-spacing: -0.01em;
  text-shadow: 0 2px 24px rgba(0, 0, 0, 0.35);
}
.login-title-rule {
  width: 72px; height: 3px; margin-top: 18px; border-radius: 3px;
  background: linear-gradient(90deg, #C9A227, #E8C040);
}
.login-feature {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 500;
  color: rgba(226, 234, 255, 0.88);
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.12);
}
.login-feature svg { color: #E8C040; flex-shrink: 0; }

/* ── Footer ───────────────────────────────────────────────────────────────── */
.login-footer {
  color: rgba(186, 210, 255, 0.55);
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(10, 19, 54, 0.45);
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

/* ── Entrance animations ──────────────────────────────────────────────────── */
.hero-enter {
  animation: heroIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.05s both;
}
@keyframes heroIn {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0);    }
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
  border-color: rgba(201, 162, 39,0.50);
  background: rgba(201, 162, 39,0.08);
}
.remember-on {
  background: #C9A227;
  border: 1.5px solid #C9A227;
  color: #22305C;
  box-shadow: 0 0 0 3px rgba(201, 162, 39,0.18);
}

/* ── Input ────────────────────────────────────────────────────────────────── */
.input-field::placeholder {
  color: rgba(168, 184, 232, 0.26);
}

/* Chrome/Edge force a light autofill background that can't be overridden
   via background-color directly — this is the standard workaround: an
   absurdly long background-color transition delays the forced repaint
   indefinitely, and the inset box-shadow paints over it in the meantime.
   Without this, a saved/autofilled credential would flash a jarring
   white-on-dark box that clashes with the glass card. */
.input-field:-webkit-autofill,
.input-field:-webkit-autofill:hover,
.input-field:-webkit-autofill:focus {
  -webkit-text-fill-color: #FFFFFF;
  -webkit-box-shadow: 0 0 0px 1000px rgba(30, 44, 84, 0.85) inset;
  box-shadow: 0 0 0px 1000px rgba(30, 44, 84, 0.85) inset;
  transition: background-color 9999s ease-in-out 0s;
  caret-color: #FFFFFF;
}

/* ── Error transition ─────────────────────────────────────────────────────── */
.err-enter-active { animation: errIn  0.28s cubic-bezier(0.34, 1.56, 0.64, 1); }
.err-leave-active { animation: errOut 0.18s ease-in; }
@keyframes errIn  { from { opacity: 0; transform: translateY(-6px) scale(0.97); } to { opacity: 1; transform: none; } }
@keyframes errOut { to   { opacity: 0; transform: translateY(-4px) scale(0.97); }                                     }
</style>
