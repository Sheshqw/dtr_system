<template>
  <div v-if="isVisible" ref="root" class="nb-wrap">
    <button
      @click="open = !open"
      class="nb-trigger"
      :class="{ 'nb-trigger--active': open, 'nb-trigger--critical': hasCritical }"
      :aria-expanded="open"
      aria-label="Notifications"
    >
      <BellIcon class="w-4 h-4" />
      <span v-if="total > 0" class="nb-badge" :class="{ 'nb-badge--critical': hasCritical }">{{ total > 99 ? '99+' : total }}</span>
    </button>

    <Transition name="nb-pop">
      <div v-if="open" class="nb-panel" role="menu">
        <div class="nb-panel-head">
          <p class="nb-panel-title">Notifications</p>
          <button @click="load" class="nb-refresh" :disabled="loading" title="Refresh" aria-label="Refresh">
            <RefreshCwIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
          </button>
        </div>

        <div v-if="loading && !loaded" class="nb-state">
          <Loader2Icon class="w-5 h-5 animate-spin" style="color:#94A3B8;" />
        </div>

        <div v-else-if="alerts.length === 0" class="nb-state">
          <div class="nb-empty-icon"><CircleCheckBigIcon class="w-5 h-5" /></div>
          <p class="nb-empty-title">All caught up</p>
          <p class="nb-empty-sub">No pending items need your attention.</p>
        </div>

        <div v-else class="nb-list">
          <button v-for="a in alerts" :key="a.id" class="nb-item" @click="go(a)">
            <span class="nb-item-icon" :class="`nb-item-icon--${a.severity}`">
              <component :is="iconFor(a.icon)" class="w-4 h-4" />
            </span>
            <span class="nb-item-body">
              <span class="nb-item-title">{{ a.title }}</span>
              <span class="nb-item-msg">{{ a.message }}</span>
            </span>
            <span class="nb-item-count" :class="`nb-item-count--${a.severity}`">{{ a.count }}</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import {
  Bell           as BellIcon,
  RefreshCw      as RefreshCwIcon,
  Loader2        as Loader2Icon,
  CircleCheckBig as CircleCheckBigIcon,
  ClipboardEdit  as ClipboardEditIcon,
  ShieldAlert    as ShieldAlertIcon,
  AlertTriangle  as AlertTriangleIcon,
} from 'lucide-vue-next';

const router  = useRouter();
const auth    = useAuthStore();
const root    = ref(null);
const open    = ref(false);
const loading = ref(false);
const loaded  = ref(false);
const alerts  = ref([]);
const total   = ref(0);

const hasCritical = computed(() => alerts.value.some(a => a.severity === 'critical'));
const isVisible   = computed(() => ['super_admin', 'admin', 'hr_officer'].includes(auth.employee?.role));

const ICONS = {
  'clipboard-edit': ClipboardEditIcon,
  'shield-alert':   ShieldAlertIcon,
  'alert-triangle': AlertTriangleIcon,
};
function iconFor(name) {
  return ICONS[name] ?? AlertTriangleIcon;
}

async function load() {
  loading.value = true;
  try {
    const res = await axios.get('/api/alerts');
    alerts.value = res.data.alerts ?? [];
    total.value  = res.data.total ?? 0;
  } catch {
    // Silent — this is a secondary widget, not a critical flow.
  } finally {
    loading.value = false;
    loaded.value  = true;
  }
}

function go(alert) {
  open.value = false;
  router.push({ name: alert.route });
}

function onDocClick(e) {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}
function onEsc(e) {
  if (e.key === 'Escape') open.value = false;
}

let pollTimer = null;
onMounted(() => {
  if (!isVisible.value) return;
  load();
  pollTimer = setInterval(load, 60_000);
  document.addEventListener('click', onDocClick);
  document.addEventListener('keydown', onEsc);
});
onBeforeUnmount(() => {
  clearInterval(pollTimer);
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onEsc);
});
</script>

<style scoped>
.nb-wrap { position: relative; }

/* ── Trigger button ──────────────────────────────────── */
.nb-trigger {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  transition: background 0.15s, color 0.15s, transform 0.1s;
  position: relative;
}
.nb-trigger:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.32); }
.nb-trigger:active { transform: scale(0.94); }
.nb-trigger--active { background: rgba(255,255,255,0.18) !important; border-color: rgba(201,162,39,0.6) !important; box-shadow: 0 0 0 3px rgba(201,162,39,0.16); }
.nb-trigger--critical { animation: nb-ring 2.2s ease-in-out infinite; }
@keyframes nb-ring {
  0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.35); }
  50%      { box-shadow: 0 0 0 5px rgba(220,38,38,0); }
}

/* ── Badge ────────────────────────────────────────────── */
.nb-badge {
  position: absolute; top: -4px; right: -4px;
  min-width: 17px; height: 17px; padding: 0 4px;
  border-radius: 9px; background: #D97706; color: #fff;
  font-size: 9.5px; font-weight: 800; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 0 2px #fff;
}
.nb-badge--critical { background: #DC2626; }

/* ── Dropdown panel ──────────────────────────────────── */
.nb-panel {
  position: absolute; top: calc(100% + 10px); right: 0;
  width: 340px; max-width: calc(100vw - 32px);
  background: #fff; border-radius: 14px;
  border: 1px solid #E4E9F2;
  box-shadow: 0 16px 40px rgba(8,14,128,0.16), 0 4px 12px rgba(8,14,128,0.08);
  overflow: hidden; z-index: 60;
}
.nb-panel-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px; border-bottom: 1px solid #F1F5F9;
}
.nb-panel-title { font-size: 13px; font-weight: 700; color: #0F172A; }
.nb-refresh {
  width: 24px; height: 24px; border-radius: 6px; color: #94A3B8;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.15s, color 0.15s;
}
.nb-refresh:hover:not(:disabled) { background: #F1F5F9; color: #475569; }

/* ── Empty / loading state ───────────────────────────── */
.nb-state { padding: 34px 20px; display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; }
.nb-empty-icon {
  width: 40px; height: 40px; border-radius: 12px; margin-bottom: 4px;
  background: #ECFDF5; color: #059669;
  display: flex; align-items: center; justify-content: center;
}
.nb-empty-title { font-size: 12.5px; font-weight: 600; color: #0F172A; }
.nb-empty-sub   { font-size: 11.5px; color: #94A3B8; max-width: 220px; line-height: 1.4; }

/* ── List items ───────────────────────────────────────── */
.nb-list { max-height: 360px; overflow-y: auto; padding: 6px; }
.nb-item {
  width: 100%; display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 8px; border-radius: 10px; text-align: left;
  transition: background 0.12s;
}
.nb-item:hover { background: #F7F8FD; }
.nb-item + .nb-item { margin-top: 1px; }

.nb-item-icon {
  width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.nb-item-icon--critical { background: #FEF2F2; color: #DC2626; }
.nb-item-icon--warning  { background: #FFFBEB; color: #D97706; }

.nb-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.nb-item-title { font-size: 12.5px; font-weight: 600; color: #0F172A; }
.nb-item-msg   { font-size: 11.5px; color: #64748B; line-height: 1.35; }

.nb-item-count {
  flex-shrink: 0; min-width: 22px; height: 22px; padding: 0 6px;
  border-radius: 999px; font-size: 11px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin-top: 1px;
}
.nb-item-count--critical { background: #FEE2E2; color: #991B1B; }
.nb-item-count--warning  { background: #FEF3C7; color: #92400E; }

/* ── Transition ───────────────────────────────────────── */
.nb-pop-enter-active { transition: opacity 0.16s ease, transform 0.16s cubic-bezier(0.22, 1, 0.36, 1); }
.nb-pop-leave-active { transition: opacity 0.12s ease, transform 0.12s ease; }
.nb-pop-enter-from, .nb-pop-leave-to { opacity: 0; transform: translateY(-6px) scale(0.98); }

/* ── Mobile ──────────────────────────────────────────── */
@media (max-width: 420px) {
  .nb-panel { position: fixed; top: 60px; right: 12px; left: 12px; width: auto; }
}
</style>
