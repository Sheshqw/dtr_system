<template>
  <div ref="root" class="sp-wrap">
    <button
      ref="triggerEl"
      @click="toggle"
      class="sp-trigger"
      :class="{ 'sp-trigger--active': open }"
      :aria-expanded="open"
      aria-label="Display settings"
      title="Display settings"
    >
      <SettingsIcon class="w-4 h-4" />
    </button>

    <!-- Teleported to <body> for the same reason as NotificationBell: a
         header bar clips its own overflow to contain its decorative
         background, which would clip this panel too if left nested. -->
    <Teleport to="body">
      <Transition name="sp-pop">
        <div v-if="open" ref="panelEl" class="sp-panel" role="menu" :style="panelStyle">
          <p class="sp-label">Appearance</p>
          <div class="sp-seg" role="group" aria-label="Theme mode">
            <button
              v-for="opt in themeOptions" :key="opt.mode"
              @click="theme.setMode(opt.mode)"
              class="sp-seg-btn"
              :class="theme.mode === opt.mode ? 'sp-seg-btn--active' : ''"
              :aria-pressed="theme.mode === opt.mode"
            >
              <component :is="opt.icon" class="w-3.5 h-3.5" />
              {{ opt.label }}
            </button>
          </div>

          <p class="sp-label sp-label--mt">Text Size</p>
          <div class="sp-seg" role="group" aria-label="Text size">
            <button
              v-for="opt in textSizeOptions" :key="opt.level"
              @click="textSize.setLevel(opt.level)"
              class="sp-seg-btn"
              :class="textSize.level === opt.level ? 'sp-seg-btn--active' : ''"
              :aria-pressed="textSize.level === opt.level"
              :title="opt.title"
            >
              <span :style="{ fontSize: opt.glyph }">A</span>
              {{ opt.label }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useThemeStore } from '@/stores/theme';
import { useTextSizeStore } from '@/stores/textSize';
import { Settings as SettingsIcon, Sun as SunIcon, Moon as MoonIcon } from 'lucide-vue-next';

const theme    = useThemeStore();
const textSize = useTextSizeStore();

const root      = ref(null);
const triggerEl = ref(null);
const panelEl   = ref(null);
const open      = ref(false);
const panelStyle = ref({});

const themeOptions = [
  { mode: 'light', label: 'Light', icon: SunIcon },
  { mode: 'dark',  label: 'Dark',  icon: MoonIcon },
];
const textSizeOptions = [
  { level: 'sm', label: 'Small',  glyph: '11px', title: 'Smaller text' },
  { level: 'md', label: 'Normal', glyph: '13px', title: 'Default text size' },
  { level: 'lg', label: 'Large',  glyph: '16px', title: 'Larger text — easier to read' },
];

function positionPanel() {
  const rect = triggerEl.value?.getBoundingClientRect();
  if (!rect) return;
  panelStyle.value = {
    top:   `${rect.bottom + 10}px`,
    right: `${Math.max(16, window.innerWidth - rect.right)}px`,
  };
}

function toggle() {
  if (!open.value) positionPanel();
  open.value = !open.value;
}

function onDocClick(e) {
  if (!open.value) return;
  if (root.value?.contains(e.target)) return;
  if (panelEl.value?.contains(e.target)) return;
  open.value = false;
}
function onEsc(e) { if (e.key === 'Escape') open.value = false; }
function onScroll() { if (open.value) open.value = false; }
function onResize() { if (open.value) positionPanel(); }

onMounted(() => {
  document.addEventListener('click', onDocClick);
  document.addEventListener('keydown', onEsc);
  window.addEventListener('scroll', onScroll, { capture: true, passive: true });
  window.addEventListener('resize', onResize);
});
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onEsc);
  window.removeEventListener('scroll', onScroll, { capture: true });
  window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.sp-wrap { position: relative; }

.sp-trigger {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  color: #fff;
  transition: background 0.15s, color 0.15s, transform 0.1s;
}
.sp-trigger:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.32); }
.sp-trigger:active { transform: scale(0.94); }
.sp-trigger--active { background: rgba(255,255,255,0.18); border-color: rgba(201, 162, 39,0.6); box-shadow: 0 0 0 3px rgba(201, 162, 39,0.16); }

.sp-panel {
  position: fixed; z-index: 100; width: 240px;
  background: #fff; border-radius: 12px;
  border: 1px solid #E4E9F2;
  box-shadow: 0 12px 32px rgba(15,23,42,0.16), 0 2px 8px rgba(15,23,42,0.08);
  padding: 14px;
}
:root[data-theme="dark"] .sp-panel { background: #1E2A4A; border-color: rgba(255,255,255,0.1); }

.sp-label {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: #94A3B8; margin-bottom: 6px;
}
.sp-label--mt { margin-top: 14px; }

.sp-seg { display: flex; gap: 6px; }
.sp-seg-btn {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px;
  padding: 8px 4px; border-radius: 8px; font-size: 10.5px; font-weight: 600;
  color: #64748B; background: #F4F6FB; border: 1px solid transparent;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.sp-seg-btn:hover { background: #EEF2FF; }
.sp-seg-btn--active {
  background: rgba(46, 74, 140,0.1); color: #2E4A8C; border-color: rgba(46, 74, 140,0.3);
}
:root[data-theme="dark"] .sp-seg-btn { background: rgba(255,255,255,0.06); color: #B8C2DC; }
:root[data-theme="dark"] .sp-seg-btn--active { background: rgba(201,162,39,0.18); color: #E8C040; border-color: rgba(201,162,39,0.4); }

/* Transition */
.sp-pop-enter-active, .sp-pop-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.sp-pop-enter-from, .sp-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(0.98); }
</style>
