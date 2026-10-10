<template>
  <!-- The frame reserves the widget's (scaled) height so the form never jumps. -->
  <div ref="frame" class="turnstile-frame" :style="{ height: `${widgetHeight * scale}px` }">
    <div ref="container" class="turnstile-widget" :style="widgetStyle"></div>
    <p v-if="status === 'loading'" class="turnstile-note">Loading security check…</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

/**
 * Cloudflare Turnstile widget (explicit rendering). Emits the one-time token
 * the server verifies on login. Tokens are single-use and expire after ~5
 * minutes, so the parent calls reset() after every failed submit.
 */
const props = defineProps({
  siteKey: { type: String, required: true },
  theme:   { type: String, default: 'dark' },
});

const emit = defineEmits(['verified', 'expired', 'error']);

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

// Turnstile's 'flexible' widget is 65px tall and fills its container, but
// never gets narrower than 300px. On phones where the login card is narrower
// than that, the slim bar is scaled down uniformly to the card's width
// instead of overflowing it (or switching to the tall 150×140 compact box).
const WIDGET_MIN_WIDTH = 300;
const WIDGET_HEIGHT    = 65;

// Shared across mounts — the script must only ever be added to the page once.
let scriptPromise = null;

function loadScript() {
  if (window.turnstile) return Promise.resolve(window.turnstile);
  if (scriptPromise) return scriptPromise;

  scriptPromise = new Promise((resolve, reject) => {
    const script   = document.createElement('script');
    script.src     = SCRIPT_URL;
    script.async   = true;
    script.onload  = () => resolve(window.turnstile);
    script.onerror = () => {
      scriptPromise = null; // allow a retry on the next mount
      script.remove();
      reject(new Error('Turnstile script failed to load'));
    };
    document.head.appendChild(script);
  });

  return scriptPromise;
}

const frame     = ref(null);
const container = ref(null);
const status    = ref('loading'); // loading | ready | error
const scale     = ref(1);
const widgetHeight = ref(WIDGET_HEIGHT);
let widgetId    = null;
let resizeObserver = null;

const widgetStyle = computed(() => scale.value < 1
  ? { width: `${WIDGET_MIN_WIDTH}px`, transform: `scale(${scale.value})` }
  : { width: '100%' }
);

// offsetHeight ignores the CSS transform, so this is the widget's real height
// (65px normally; Cloudflare can add a notice strip, e.g. with test keys).
function updateFit() {
  const width = frame.value?.clientWidth ?? 0;
  scale.value = width > 0 ? Math.min(1, width / WIDGET_MIN_WIDTH) : 1;
  widgetHeight.value = container.value?.offsetHeight || WIDGET_HEIGHT;
}

onMounted(async () => {
  updateFit();
  // Keeps the fit right if the phone is rotated while on the login page, or
  // if Cloudflare changes the widget's height after it renders.
  resizeObserver = new ResizeObserver(updateFit);
  resizeObserver.observe(frame.value);
  resizeObserver.observe(container.value);

  try {
    const turnstile = await loadScript();
    if (!container.value) return; // unmounted while the script was loading

    widgetId = turnstile.render(container.value, {
      sitekey: props.siteKey,
      theme:   props.theme,
      size:    'flexible',
      action:  'login',
      callback:           (token) => { status.value = 'ready'; emit('verified', token); },
      'expired-callback': ()      => emit('expired'),
      'error-callback':   (code)  => { status.value = 'error'; emit('error', code); },
    });
    status.value = 'ready';
  } catch {
    status.value = 'error';
    emit('error', 'script-load-failed');
  }
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  if (widgetId !== null && window.turnstile) {
    window.turnstile.remove(widgetId);
  }
});

/** Get a fresh token — call after a failed login, since the old one is spent. */
function reset() {
  if (widgetId !== null && window.turnstile) {
    window.turnstile.reset(widgetId);
  }
}

defineExpose({ reset });
</script>

<style scoped>
.turnstile-frame {
  position: relative;
  width: 100%;
}
.turnstile-widget {
  transform-origin: top left;
}
.turnstile-note {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: rgba(168, 184, 232, 0.45);
}
</style>
