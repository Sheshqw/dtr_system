<template>
  <div class="login-page min-h-screen flex flex-col">

    <!-- ── University header bar ───────────────────────────────────────── -->
    <header class="login-header">
      <div class="px-5 sm:px-8 lg:px-12 h-16 flex items-center gap-3">
        <img
          :src="settings.logoUrl"
          :alt="settings.systemName"
          class="w-10 h-10 rounded-full object-cover flex-shrink-0"
          style="box-shadow: 0 0 0 2px rgba(247, 165, 26, 0.85), 0 4px 14px rgba(0, 0, 0, 0.30);"
        />
        <div class="min-w-0 leading-tight">
          <p class="text-[9.5px] font-semibold tracking-[0.22em] uppercase" style="color: rgba(214, 220, 255, 0.75);">Republic of the Philippines</p>
          <p class="login-uni text-white text-[13px] sm:text-[15px] truncate">North Eastern Mindanao State University</p>
        </div>
        <div class="ml-auto login-clock" :aria-label="`Philippine time: ${clock.date}, ${clock.time}`">
          <span class="login-clock-dot" aria-hidden="true"></span>
          <span class="hidden md:inline login-clock-date">{{ clock.date }}</span>
          <span class="login-clock-time">{{ clock.time }}</span>
        </div>
      </div>
      <div class="login-header-rule"></div>
    </header>

    <main class="flex-1 grid grid-rows-[auto_1fr] lg:grid-rows-1 lg:grid-cols-2">

      <!-- ── Campus slideshow ────────────────────────────────────────────
           The right half on desktop, a banner across the top on phones:
           the university photo first, then every active campus that has a
           photo (Campuses page). -->
      <div class="login-photo lg:order-2">
        <CampusSlideshow :slides="slides" />
      </div>

      <!-- ── Title + sign-in ──────────────────────────────────────────── -->
      <div class="login-panel lg:order-1">
        <img :src="settings.logoUrl" alt="" class="login-watermark" aria-hidden="true" />

        <div class="relative w-full max-w-[440px] mx-auto">
          <section class="hero-enter text-center lg:text-left mb-6">
            <p class="text-[11px] font-bold tracking-[0.26em] uppercase" style="color: #F7A51A;">
              Human Resource Management Office
            </p>
            <h1 class="login-title mt-2.5 text-white">
              {{ settings.tagline }}
            </h1>
            <div class="login-title-rule mx-auto lg:mx-0"></div>
            <p class="login-blurb hidden sm:block mt-4 text-[14px] leading-relaxed" style="color: rgba(226, 230, 255, 0.82);">
              Biometric attendance, daily time records and the official CSC Form 48 for every
              {{ settings.systemName }} campus.
            </p>
          </section>

          <!-- ── Glass card ────────────────────────────────────────────── -->
          <div class="card-enter card-glass rounded-3xl overflow-hidden w-full">

            <!-- Top accent bar: royal blue → gold, the university's colours -->
            <div class="h-[3px] w-full" style="background: linear-gradient(90deg, #2A1FCF 0%, #4C6BFF 35%, #F7A51A 70%, #FFBE45 100%);"></div>

            <div class="px-8 pt-7 pb-8">

              <!-- Card header -->
              <div class="mb-6 text-center">
                <h2 class="text-[21px] font-bold leading-tight text-white">Welcome back</h2>
                <p class="text-[13px] mt-1" style="color: rgba(214, 220, 255, 0.75);">Sign in to your account to continue</p>
              </div>
              <form @submit.prevent="handleLogin" class="space-y-4" novalidate>

                <!-- Email -->
                <div>
                  <label for="login-email" class="block text-[10px] font-bold tracking-[0.18em] uppercase mb-2"
                         style="color: rgba(214, 220, 255, 0.72);">
                    Email Address
                  </label>
                  <div class="relative">
                    <MailIcon
                      class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                      :style="{ color: focused === 'email' ? '#F7A51A' : 'rgba(214, 220, 255, 0.50)' }"
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
                         style="color: rgba(214, 220, 255, 0.72);">
                    Password
                  </label>
                  <div class="relative">
                    <LockIcon
                      class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none transition-colors duration-200"
                      :style="{ color: focused === 'pwd' ? '#F7A51A' : 'rgba(214, 220, 255, 0.50)' }"
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
                      :style="{ color: showPwd ? '#F7A51A' : 'rgba(214, 220, 255, 0.55)' }"
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
                    style="color: rgba(214, 220, 255, 0.78);"
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
                  class="relative w-full py-3.5 text-sm font-bold rounded-xl overflow-hidden transition-all duration-200 disabled:opacity-70 disabled:cursor-not-allowed group mt-1"
                  :style="btnStyle"
                >
                  <div
                    class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"
                    style="background: linear-gradient(135deg, rgba(255,255,255,0.20) 0%, transparent 60%);"
                  ></div>
                  <span v-if="loading" class="relative flex items-center justify-center gap-2.5 text-[#1A138F]">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Authenticating…
                  </span>
                  <span v-else class="relative flex items-center justify-center gap-2 text-[#1A138F]">
                    Sign In
                    <ArrowRightIcon class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" />
                  </span>
                </button>

              </form>

              <!-- Card footer -->
              <div class="mt-6 pt-5" style="border-top: 1px solid rgba(255,255,255,0.10);">
                <p class="text-center text-[10.5px]" style="color: rgba(214, 220, 255, 0.60);">
                  Authorized personnel only &nbsp;&middot;&nbsp; Activity is recorded in the audit trail
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>

    <!-- ── Footer ──────────────────────────────────────────────────────── -->
    <footer class="login-footer">
      <div class="px-5 sm:px-8 lg:px-12 py-4 flex flex-col sm:flex-row items-center justify-between gap-1 text-[11px] text-center">
        <span>&copy; {{ currentYear }} North Eastern Mindanao State University &middot; Surigao del Sur</span>
        <span>{{ settings.shortName }} &middot; Daily Time Record System v1.0</span>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import CampusSlideshow from '@/components/CampusSlideshow.vue';
import {
  Mail          as MailIcon,
  Lock          as LockIcon,
  Eye           as EyeIcon,
  EyeOff        as EyeOffIcon,
  AlertCircle   as AlertCircleIcon,
  ArrowRight    as ArrowRightIcon,
  Check         as CheckIcon,
  TriangleAlert as TriangleAlertIcon,
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

// The university photo that opens the slideshow, until the Super Admin
// uploads one of their own in System Settings. The building's facade reads
// "Administration"; which campus it stands on isn't confirmed, so the
// caption names the building and the university only.
const DEFAULT_CAMPUS_PHOTO = '/images/nemsu-campus.jpg';

const slides = computed(() => [
  settings.loginBackgroundUrl
    ? { photo_url: settings.loginBackgroundUrl, name: 'North Eastern Mindanao State University' }
    : {
        photo_url: DEFAULT_CAMPUS_PHOTO,
        name:      'Administration Building',
        subtitle:  'North Eastern Mindanao State University',
        credit:    'Photo: NEMSU',
      },
  ...settings.campusSlides.map((c) => ({
    photo_url:  c.photo_url,
    name:       c.name,
    subtitle:   c.address || 'North Eastern Mindanao State University',
    credit:     c.credit,
    credit_url: c.credit_url,
  })),
]);

// ── Live clock — Philippine time, wherever the visitor's device is set ──
const dateFmt = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
const timeFmt = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
const clock = reactive({ date: '', time: '' });
function tick() {
  const now = new Date();
  clock.date = dateFmt.format(now);
  clock.time = timeFmt.format(now);
}
tick();
let clockTimer = null;
onMounted(() => { clockTimer = setInterval(tick, 1000); });
onBeforeUnmount(() => clearInterval(clockTimer));

function inputStyle(field) {
  const active = focused.value === field;
  return {
    background: active ? 'rgba(255,255,255,0.10)' : 'rgba(255,255,255,0.055)',
    border:     `1.5px solid ${active ? 'rgba(247, 165, 26,0.65)' : 'rgba(255,255,255,0.10)'}`,
    boxShadow:  active ? '0 0 0 3px rgba(247, 165, 26,0.12)' : 'none',
    color:      '#FFFFFF',
  };
}

const btnStyle = computed(() => ({
  background:     'linear-gradient(135deg, #F7A51A, #FFBE45, #E99B19)',
  backgroundSize: '200% 100%',
  boxShadow:      loading.value ? 'none' : '0 4px 28px rgba(247, 165, 26,0.45), 0 2px 8px rgba(0,0,0,0.25)',
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
/* ── Page ─────────────────────────────────────────────────────────────────────
   The official NEMSU palette (nemsu.edu.ph / sis.nemsu.edu.ph): royal blue
   #1C0ED7 / #2A1FCF, deep indigo #1A138F and gold #F7A51A / #E99B19. */
.login-page { background: #1A138F; }

/* ── Campus photo ─────────────────────────────────────────────────────────── */
.login-photo {
  position: relative;
  height: 210px;
  overflow: hidden;
  background: #1A138F;
}
@media (min-width: 1024px) {
  .login-photo { height: auto; min-height: 520px; }
}
/* Soften the seam between the photos and the blue panel. */
.login-photo::after {
  content: '';
  position: absolute; z-index: 2;
  inset: auto 0 0 0; height: 36px;
  background: linear-gradient(0deg, rgba(28, 14, 215, 0.85), transparent);
  pointer-events: none;
}
@media (min-width: 1024px) {
  .login-photo::after {
    inset: 0 auto 0 0; width: 56px; height: auto;
    background: linear-gradient(90deg, rgba(26, 19, 143, 0.9), transparent);
  }
}

/* ── Title + sign-in panel ────────────────────────────────────────────────── */
.login-panel {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: 32px 20px 40px;
  background:
    radial-gradient(120% 80% at 0% 0%, rgba(76, 107, 255, 0.35) 0%, transparent 60%),
    linear-gradient(160deg, #2A1FCF 0%, #1C0ED7 45%, #1A138F 100%);
}
@media (min-width: 1024px) {
  .login-panel { padding: 28px 48px; }
}
/* On short screens the one-line description gives way, so the card and
   footer still fit without scrolling. */
@media (max-height: 820px) {
  .login-blurb { display: none !important; }
}
/* The seal, faint and oversized — the same touch as the SIS site's footer. */
.login-watermark {
  position: absolute;
  width: 420px; height: 420px;
  left: -190px; bottom: -190px;
  opacity: 0.05;
  filter: grayscale(1) brightness(2);
  pointer-events: none;
}

/* ── University header bar ────────────────────────────────────────────────── */
.login-header {
  position: relative; z-index: 2;
  background: #1A138F;
}
/* Gold rule under the header, like the university site's navigation bar. */
.login-header-rule {
  height: 3px;
  background: linear-gradient(90deg, #F7A51A 0%, #FFBE45 45%, #E99B19 100%);
}
.login-uni {
  font-family: var(--font-serif);
  font-weight: 600;
  letter-spacing: 0.01em;
}
/* Live clock pill */
.login-clock {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid rgba(247, 165, 26, 0.45);
  background: rgba(247, 165, 26, 0.10);
  white-space: nowrap;
}
.login-clock-date { font-size: 12px; color: rgba(226, 230, 255, 0.85); }
.login-clock-time {
  font-size: 13px; font-weight: 700; color: #FFBE45;
  font-variant-numeric: tabular-nums;
  min-width: 7.6ch; text-align: right;
}
.login-clock-dot {
  width: 7px; height: 7px; border-radius: 999px; background: #34D399;
  box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.6);
  animation: clockPulse 2s ease-out infinite;
}
/* On phones the university's name needs the whole header. */
@media (max-width: 639px) {
  .login-clock { display: none; }
}
@keyframes clockPulse {
  0%   { box-shadow: 0 0 0 0   rgba(52, 211, 153, 0.6); }
  70%  { box-shadow: 0 0 0 6px rgba(52, 211, 153, 0);   }
  100% { box-shadow: 0 0 0 0   rgba(52, 211, 153, 0);   }
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.login-title {
  font-family: var(--font-serif);
  font-weight: 700;
  font-size: clamp(26px, 2.6vw, 36px);
  line-height: 1.15;
  letter-spacing: -0.01em;
  text-shadow: 0 2px 18px rgba(0, 0, 0, 0.25);
}
.login-title-rule {
  width: 72px; height: 3px; margin-top: 16px; border-radius: 3px;
  background: linear-gradient(90deg, #F7A51A, #FFBE45);
}
/* ── Footer ───────────────────────────────────────────────────────────────── */
.login-footer {
  color: rgba(214, 220, 255, 0.70);
  background: #140E70;
  border-top: 3px solid #F7A51A;
}

/* ── Glass card ───────────────────────────────────────────────────────────── */
.card-glass {
  background: rgba(14, 9, 86, 0.55);
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  border: 1px solid rgba(255, 255, 255, 0.09);
  box-shadow:
    0 48px 110px rgba(0, 0, 0, 0.60),
    0 14px 44px  rgba(0, 0, 0, 0.32),
    0  0    0 1px rgba(247, 165, 26, 0.08),
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
  border-color: rgba(247, 165, 26,0.50);
  background: rgba(247, 165, 26,0.08);
}
.remember-on {
  background: #F7A51A;
  border: 1.5px solid #F7A51A;
  color: #1A138F;
  box-shadow: 0 0 0 3px rgba(247, 165, 26,0.18);
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
  -webkit-box-shadow: 0 0 0px 1000px rgba(30, 22, 140, 0.92) inset;
  box-shadow: 0 0 0px 1000px rgba(30, 22, 140, 0.92) inset;
  transition: background-color 9999s ease-in-out 0s;
  caret-color: #FFFFFF;
}

/* ── Error transition ─────────────────────────────────────────────────────── */
.err-enter-active { animation: errIn  0.28s cubic-bezier(0.34, 1.56, 0.64, 1); }
.err-leave-active { animation: errOut 0.18s ease-in; }
@keyframes errIn  { from { opacity: 0; transform: translateY(-6px) scale(0.97); } to { opacity: 1; transform: none; } }
@keyframes errOut { to   { opacity: 0; transform: translateY(-4px) scale(0.97); }                                     }
</style>
