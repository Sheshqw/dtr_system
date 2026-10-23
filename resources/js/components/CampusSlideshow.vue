<template>
  <div class="ss" role="region" aria-roledescription="carousel" aria-label="NEMSU campuses">
    <!-- Each photo is shown whole and never enlarged past its own pixels,
         so it stays sharp and keeps the campus sign in view; a blurred copy
         of the same photo fills the space around it. Only the current and
         next photos are loaded. -->
    <div
      v-for="(slide, i) in slides"
      :key="`${i}-${slide.photo_url}`"
      class="ss-slide"
      :class="{ 'is-active': i === index }"
      :aria-hidden="i !== index"
    >
      <template v-if="loaded.has(i) || i === index || i === nextIndex">
        <img :src="slide.photo_url" alt="" class="ss-backdrop" decoding="async" />
        <img
          :src="slide.photo_url"
          :alt="slide.name"
          class="ss-photo"
          decoding="async"
          @load="loaded.add(i)"
        />
      </template>
    </div>

    <!-- Caption: which campus this is -->
    <Transition name="ss-cap" mode="out-in">
      <div :key="index" class="ss-caption" aria-live="polite">
        <MapPinIcon class="ss-caption-icon" />
        <div class="min-w-0">
          <p class="ss-name">{{ current.name }}</p>
          <p v-if="current.subtitle" class="ss-sub">{{ current.subtitle }}</p>
          <p v-if="current.credit" class="ss-credit">
            <a v-if="current.credit_url" :href="current.credit_url" target="_blank" rel="noopener noreferrer">{{ current.credit }}</a>
            <template v-else>{{ current.credit }}</template>
          </p>
        </div>
      </div>
    </Transition>

    <!-- It plays by itself; the dots show where it is (and jump to a
         campus if clicked), and the button pauses it. -->
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
  // [{ photo_url, name, subtitle?, credit?, credit_url? }]
  slides:   { type: Array, required: true },
  interval: { type: Number, default: 6000 },
});

const index   = ref(0);
const cycle   = ref(0);          // restarts the active dot's fill animation
const loaded  = reactive(new Set());
const stopped = ref(false);       // the visitor pressed pause
const hidden  = ref(typeof document !== 'undefined' && document.hidden);

const current   = computed(() => props.slides[index.value] ?? props.slides[0] ?? {});
const nextIndex = computed(() => (index.value + 1) % Math.max(props.slides.length, 1));
// Only the pause button or a hidden browser tab stop it — the slideshow
// keeps going while the visitor signs in.
const paused    = computed(() => stopped.value || hidden.value);

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

/* ── Slides: a plain cross-fade ──────────────────────────────────────── */
.ss-slide {
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 1s ease;
}
.ss-slide.is-active { opacity: 1; z-index: 1; }

/* The blurred fill — heavily blurred, so its resolution never shows. */
.ss-backdrop {
  position: absolute;
  inset: -40px;
  width: calc(100% + 80px);
  height: calc(100% + 80px);
  object-fit: cover;
  filter: blur(28px) saturate(1.15) brightness(0.62);
}
/* The photo itself: whole, centred, and no larger than it really is. */
.ss-photo {
  position: absolute;
  inset: 0;
  margin: auto;
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  box-shadow: 0 18px 50px rgba(8, 5, 50, 0.45);
}

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
  background: rgba(14, 9, 86, 0.78);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 8px 24px rgba(10, 6, 60, 0.35);
}
.ss-caption-icon { width: 18px; height: 18px; color: #F7A51A; flex-shrink: 0; }
.ss-name   { font-size: 15px; font-weight: 700; color: #fff; line-height: 1.2; }
.ss-sub    { font-size: 12px; color: rgba(226, 230, 255, 0.85); margin-top: 2px; }
.ss-credit { font-size: 10.5px; color: rgba(226, 230, 255, 0.6); margin-top: 3px; }
.ss-credit a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
.ss-credit a:hover { color: #FFBE45; }

.ss-cap-enter-active { transition: opacity 0.4s ease 0.3s, transform 0.4s ease 0.3s; }
.ss-cap-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.ss-cap-enter-from   { opacity: 0; transform: translateY(8px); }
.ss-cap-leave-to     { opacity: 0; transform: translateY(-4px); }

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
  /* The strip is short on phones — name and credit only. */
  .ss-caption  { left: 10px; bottom: 8px; gap: 7px; padding: 5px 10px 5px 8px; border-radius: 9px; }
  .ss-caption-icon { width: 14px; height: 14px; }
  .ss-name     { font-size: 12px; }
  .ss-sub      { display: none; }
  .ss-credit   { font-size: 9px; margin-top: 1px; }
  .ss-controls { top: 10px; right: 10px; }
}
</style>
