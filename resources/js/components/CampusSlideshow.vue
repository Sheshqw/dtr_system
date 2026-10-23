<template>
  <div
    class="ss"
    role="region"
    aria-roledescription="carousel"
    aria-label="NEMSU campuses"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    @focusin="focused = true"
    @focusout="focused = false"
  >
    <!-- Photos are stacked and cross-faded; only the current one and the
         next are loaded, so the login page never waits on the whole set. -->
    <div
      v-for="(slide, i) in slides"
      :key="`${i}-${slide.photo_url}`"
      class="ss-slide"
      :class="{ 'is-active': i === index, 'is-still': slides.length === 1 }"
      :style="{ '--ss-duration': `${interval + FADE_MS}ms` }"
      :aria-hidden="i !== index"
    >
      <img
        v-if="loaded.has(i) || i === index || i === nextIndex"
        :src="slide.photo_url"
        alt=""
        :style="{ objectPosition: slide.position ?? 'center' }"
        decoding="async"
        @load="loaded.add(i)"
      />
    </div>

    <!-- Caption: which campus this is -->
    <Transition name="ss-cap" mode="out-in">
      <div :key="index" class="ss-caption" aria-live="polite">
        <MapPinIcon class="ss-caption-icon" />
        <div class="min-w-0">
          <p class="ss-name">{{ current.name }}</p>
          <p v-if="current.subtitle" class="ss-sub">{{ current.subtitle }}</p>
        </div>
      </div>
    </Transition>

    <!-- Controls: pause/play, then one dot per campus; the active dot fills
         as its photo's time runs out. -->
    <div v-if="slides.length > 1" class="ss-controls">
      <button
        type="button"
        class="ss-toggle"
        :aria-label="stopped ? 'Play slideshow' : 'Pause slideshow'"
        @click="stopped = !stopped"
      >
        <PlayIcon v-if="stopped" class="w-3 h-3" />
        <PauseIcon v-else class="w-3 h-3" />
      </button>
      <button
        v-for="(slide, i) in slides"
        :key="`${i}-${slide.photo_url}`"
        type="button"
        class="ss-dot"
        :class="{ 'is-active': i === index }"
        :aria-label="`Show ${slide.name}`"
        :aria-current="i === index ? 'true' : undefined"
        @click="go(i)"
      >
        <span
          v-if="i === index"
          :key="cycle"
          class="ss-dot-fill"
          :class="{ 'is-paused': paused }"
          :style="{ animationDuration: `${interval}ms` }"
        ></span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { MapPin as MapPinIcon, Pause as PauseIcon, Play as PlayIcon } from 'lucide-vue-next';

const props = defineProps({
  // [{ photo_url, name, subtitle?, position? }]
  slides:   { type: Array, required: true },
  interval: { type: Number, default: 6500 },
});

const FADE_MS = 1200;

const index   = ref(0);
const cycle   = ref(0);          // restarts the active dot's fill animation
const loaded  = reactive(new Set());
const hovered = ref(false);
const focused = ref(false);
const stopped = ref(false);       // the visitor pressed pause
const hidden  = ref(typeof document !== 'undefined' && document.hidden);

const current   = computed(() => props.slides[index.value] ?? props.slides[0] ?? {});
const nextIndex = computed(() => (index.value + 1) % Math.max(props.slides.length, 1));
// Hovering, keyboard focus, a hidden tab or the pause button all hold the
// current photo, so nobody is rushed and a background tab costs nothing.
const paused    = computed(() => hovered.value || focused.value || stopped.value || hidden.value);

let timer = null;

function schedule() {
  clearTimeout(timer);
  timer = null;
  if (props.slides.length < 2 || paused.value) return;
  timer = setTimeout(() => go(nextIndex.value), props.interval);
}

function go(i) {
  index.value = i;
  cycle.value++;
  schedule();
}

function onVisibility() {
  hidden.value = document.hidden;
}

// Resuming starts the current photo's time over, matching its dot.
watch(paused, (isPaused) => {
  if (!isPaused) cycle.value++;
  schedule();
});

watch(() => props.slides.length, (n) => {
  if (index.value >= n) index.value = 0;
  schedule();
});

onMounted(() => {
  document.addEventListener('visibilitychange', onVisibility);
  schedule();
});

onBeforeUnmount(() => {
  clearTimeout(timer);
  document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<style scoped>
.ss {
  position: absolute;
  inset: 0;
  overflow: hidden;
  background: #1A138F;
}

/* ── Slides: cross-fade + a slow push-in while each one is on screen ── */
.ss-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 1.2s ease;
}
.ss-slide.is-active { opacity: 1; z-index: 1; }
.ss-slide img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scale(1);
  transform-origin: 60% 40%;
}
.ss-slide.is-active img {
  animation: ss-push var(--ss-duration) ease-out both;
}
/* A single photo just breathes slowly instead. */
.ss-slide.is-still img {
  animation: ss-breathe 18s ease-in-out infinite alternate;
}
@keyframes ss-push    { from { transform: scale(1); }    to { transform: scale(1.07); } }
@keyframes ss-breathe { from { transform: scale(1); }    to { transform: scale(1.05); } }

/* ── Caption ─────────────────────────────────────────────────────────── */
.ss-caption {
  position: absolute;
  z-index: 3;
  left: 20px;
  bottom: 18px;
  max-width: calc(100% - 40px);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px 10px 12px;
  border-radius: 12px;
  border-left: 3px solid #F7A51A;
  background: rgba(14, 9, 86, 0.74);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 8px 24px rgba(10, 6, 60, 0.35);
}
.ss-caption-icon { width: 18px; height: 18px; color: #F7A51A; flex-shrink: 0; }
.ss-name { font-size: 15px; font-weight: 700; color: #fff; line-height: 1.2; }
.ss-sub  { font-size: 12px; color: rgba(226, 230, 255, 0.82); margin-top: 2px; }

.ss-cap-enter-active { transition: opacity 0.45s ease 0.35s, transform 0.45s ease 0.35s; }
.ss-cap-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.ss-cap-enter-from   { opacity: 0; transform: translateY(10px); }
.ss-cap-leave-to     { opacity: 0; transform: translateY(-6px); }

/* ── Controls ────────────────────────────────────────────────────────── */
.ss-controls {
  position: absolute;
  z-index: 3;
  top: 16px;
  right: 16px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 9px 5px 5px;
  border-radius: 999px;
  background: rgba(14, 9, 86, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}
.ss-toggle {
  width: 22px; height: 22px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 999px;
  color: #fff;
  background: rgba(255, 255, 255, 0.14);
  transition: background-color 0.15s ease;
}
.ss-toggle:hover { background: rgba(255, 255, 255, 0.26); }
.ss-dot {
  position: relative;
  width: 7px; height: 7px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.65);
  transition: width 0.3s ease, background-color 0.3s ease;
}
.ss-dot:hover { background: #fff; }
.ss-dot.is-active { width: 26px; background: rgba(255, 255, 255, 0.35); }
.ss-dot-fill {
  position: absolute;
  inset: 0;
  background: #F7A51A;
  transform-origin: left center;
  animation: ss-fill linear both;
}
.ss-dot-fill.is-paused { animation-play-state: paused; }
@keyframes ss-fill { from { transform: scaleX(0); } to { transform: scaleX(1); } }

@media (max-width: 1023px) {
  .ss-caption { left: 12px; bottom: 10px; padding: 7px 12px 7px 9px; }
  .ss-name    { font-size: 13px; }
  .ss-sub     { font-size: 11px; }
  .ss-controls { top: 10px; right: 10px; }
}
</style>
