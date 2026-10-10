<template>
  <div class="turnstile-wrap">
    <div ref="container" class="w-full flex justify-center"></div>
    <p v-if="status === 'loading'" class="turnstile-note">Loading security check…</p>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

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

// Turnstile's 'flexible' size never shrinks below 300px, so on narrow phones
// it would spill out of the login card — use the official compact layout
// (150×140) there instead.
const MIN_FLEXIBLE_WIDTH = 300;

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

const container = ref(null);
const status    = ref('loading'); // loading | ready | error
let widgetId    = null;

onMounted(async () => {
  try {
    const turnstile = await loadScript();
    if (!container.value) return; // unmounted while the script was loading

    widgetId = turnstile.render(container.value, {
      sitekey: props.siteKey,
      theme:   props.theme,
      size:    container.value.clientWidth >= MIN_FLEXIBLE_WIDTH ? 'flexible' : 'compact',
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
.turnstile-wrap {
  min-height: 65px; /* reserve the widget's height so the form doesn't jump */
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.turnstile-note {
  font-size: 11px;
  color: rgba(168, 184, 232, 0.45);
}
</style>
