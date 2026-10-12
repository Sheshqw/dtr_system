import { reactive, readonly } from 'vue';

/**
 * App-wide connection state, shown by <ConnectionBanner>.
 *
 *   online       — everything normal (banner hidden)
 *   offline      — the browser itself has no internet connection
 *   unreachable  — online, but the server isn't answering (network error,
 *                  or a 502/504 from the web server in front of PHP)
 *   maintenance  — the server answered 503 (Laravel maintenance mode)
 *   restored     — just recovered; shown briefly, then back to online
 *
 * Fed by the axios interceptor (bootstrap.js) and the browser's
 * online/offline events. While not online it re-checks the server
 * (HEALTH_URL below) on a backoff, so it recovers on its own even when the
 * user isn't clicking anything.
 */
const state = reactive({
  status:   'online',
  checking: false,
});

const RETRY_DELAYS_MS = [5000, 10000, 20000, 30000]; // then stays at 30s
const RESTORED_SHOW_MS = 3500;

let retryTimer    = null;
let restoredTimer = null;
let attempt       = 0;
let pageUnloading = false;

function clearTimers() {
  clearTimeout(retryTimer);
  clearTimeout(restoredTimer);
  retryTimer = restoredTimer = null;
}

function scheduleRetry() {
  clearTimeout(retryTimer);
  const delay = RETRY_DELAYS_MS[Math.min(attempt, RETRY_DELAYS_MS.length - 1)];
  attempt++;
  retryTimer = setTimeout(checkNow, delay);
}

function setProblem(status) {
  if (pageUnloading) return;
  // Offline outranks a server problem: there's no point blaming the server
  // when the device has no connection at all.
  if (state.status === 'offline' && status !== 'offline') return;
  clearTimeout(restoredTimer);
  state.status = status;
  if (status !== 'offline') scheduleRetry();
}

function markHealthy() {
  if (state.status === 'online' || state.status === 'restored') return;
  clearTimers();
  attempt = 0;
  state.status = 'restored';
  restoredTimer = setTimeout(() => { state.status = 'online'; }, RESTORED_SHOW_MS);
}

// A real (public, lightweight) API call rather than Laravel's /up: /up keeps
// answering 200 during maintenance mode, which would flip the banner to
// "Back online" while every actual request still gets 503. This endpoint
// also exercises the database, so "healthy" means the app really works.
const HEALTH_URL = '/api/system-settings';

/** Ping the server now (also used by the banner's "Retry now" button). */
async function checkNow() {
  if (!navigator.onLine) { setProblem('offline'); return; }
  state.checking = true;
  try {
    const res = await fetch(HEALTH_URL, { cache: 'no-store', headers: { Accept: 'application/json' } });
    if (res.ok) {
      markHealthy();
    } else {
      setProblem(res.status === 503 ? 'maintenance' : 'unreachable');
    }
  } catch {
    setProblem(navigator.onLine ? 'unreachable' : 'offline');
  } finally {
    state.checking = false;
  }
}

// ── Hooks for the axios interceptor ────────────────────────────────────
const SERVER_DOWN_STATUSES = [502, 504];

export function reportResponse() {
  markHealthy();
}

export function reportRequestError(error) {
  // Requests the app cancelled itself are not connection problems.
  if (error?.code === 'ERR_CANCELED') return;

  const status = error?.response?.status;
  if (status === 503) { setProblem('maintenance'); return; }
  if (SERVER_DOWN_STATUSES.includes(status)) { setProblem('unreachable'); return; }

  // No response at all = the request never reached the server.
  if (!error?.response) setProblem(navigator.onLine ? 'unreachable' : 'offline');
}

// ── Browser events ─────────────────────────────────────────────────────
if (typeof window !== 'undefined') {
  window.addEventListener('offline', () => setProblem('offline'));
  window.addEventListener('online', () => {
    if (state.status === 'offline') state.status = 'unreachable';
    attempt = 0;
    checkNow();
  });
  // Navigating away aborts in-flight requests — don't flash the banner for that.
  window.addEventListener('pagehide', () => { pageUnloading = true; });
  window.addEventListener('pageshow', () => { pageUnloading = false; });

  if (!navigator.onLine) state.status = 'offline';
}

export function useConnectionStatus() {
  return { state: readonly(state), checkNow };
}
