<template>
  <div class="dpp" :class="{ 'dpp--compact': compact }">
    <div v-if="!compact" class="dpp-head">
      <span class="dpp-label">DTR Coverage</span>
      <span class="dpp-tag">
        <CalendarRangeIcon class="w-3.5 h-3.5" />
        Semi-monthly DTR
      </span>
    </div>

    <!-- Period cards -->
    <div class="dpp-grid" role="radiogroup" aria-label="DTR coverage">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        role="radio"
        :aria-checked="modelValue === opt.value"
        class="dpp-card"
        :class="{ 'dpp-card--active': modelValue === opt.value }"
        @click="emit('update:modelValue', opt.value)"
      >
        <span class="dpp-check" aria-hidden="true">
          <CheckIcon class="w-3 h-3" />
        </span>

        <span class="dpp-card-title">{{ opt.title }}</span>
        <span class="dpp-card-range">{{ opt.range }}</span>

        <span class="dpp-card-foot">
          <span class="dpp-state" :class="`dpp-state--${opt.state}`">
            <span class="dpp-state-dot"></span>{{ STATE_LABELS[opt.state] }}
          </span>
          <span v-if="!compact" class="dpp-weekdays">{{ opt.weekdays }} weekdays</span>
        </span>

        <span v-if="opt.consent" class="dpp-consent" :class="`dpp-consent--${opt.consent.tone}`">
          <component :is="opt.consent.icon" class="w-3 h-3 flex-shrink-0" />
          {{ opt.consent.label }}
        </span>
      </button>
    </div>

    <template v-if="!compact">
      <!-- Month timeline: the two halves, how far the month has gone, and today -->
      <div class="dpp-timeline" aria-hidden="true">
        <div class="dpp-track">
          <div
            class="dpp-seg"
            :class="{ 'dpp-seg--on': modelValue !== 'second' }"
            :style="{ width: `${splitPct}%` }"
            @click="emit('update:modelValue', 'first')"
          ></div>
          <div
            class="dpp-seg"
            :class="{ 'dpp-seg--on': modelValue !== 'first' }"
            :style="{ width: `${100 - splitPct}%` }"
            @click="emit('update:modelValue', 'second')"
          ></div>
          <div class="dpp-elapsed" :style="{ width: `${elapsedPct}%` }"></div>
          <div v-if="todayPct !== null" class="dpp-today" :style="{ left: `${todayPct}%` }">
            <span class="dpp-today-label">Today</span>
          </div>
        </div>
        <div class="dpp-scale">
          <span>{{ monthShort }} 1</span>
          <span class="dpp-scale-mid" :style="{ left: `${splitPct}%` }">15 | 16</span>
          <span>{{ monthShort }} {{ lastDay }}</span>
        </div>
      </div>

      <Transition name="dpp-note">
        <p v-if="selected.state === 'current'" key="current" class="dpp-note dpp-note--info">
          <InfoIcon class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
          <span>This period is still in progress — days after <strong>{{ todayLabel }}</strong> will show as blank until they're recorded.</span>
        </p>
        <p v-else-if="selected.state === 'upcoming'" key="upcoming" class="dpp-note dpp-note--warn">
          <ClockIcon class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
          <span>This period hasn't started yet, so its DTR has no records.</span>
        </p>
      </Transition>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  CalendarRange as CalendarRangeIcon,
  Check         as CheckIcon,
  CheckCircle2  as CheckCircle2Icon,
  Clock         as ClockIcon,
  Download      as DownloadIcon,
  Info          as InfoIcon,
  XCircle       as XCircleIcon,
} from 'lucide-vue-next';

/**
 * Coverage picker for semi-monthly (Contractual/COS and Job Order) DTRs:
 * 1st half (1–15), 2nd half (16–end) or the whole month. Each card shows
 * whether that span is completed, in progress or upcoming, and — when the
 * employee's own print requests are passed in — its print-permission status.
 */
const props = defineProps({
  modelValue: { type: String, required: true }, // 'first' | 'second' | 'full'
  month:      { type: Number, required: true },
  year:       { type: Number, required: true },
  consents:   { type: Array,  default: null },  // employee's own print requests
  compact:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const STATE_LABELS = { done: 'Completed', current: 'In progress', upcoming: 'Upcoming' };

const today      = new Date();
const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());

const lastDay    = computed(() => new Date(props.year, props.month, 0).getDate());
const monthShort = computed(() => new Date(props.year, props.month - 1, 1).toLocaleDateString('en-PH', { month: 'short' }));
const splitPct   = computed(() => (15 / lastDay.value) * 100);

const isCurrentMonth = computed(() =>
  props.year === today.getFullYear() && props.month === today.getMonth() + 1
);

function stateOf(startDay, endDay) {
  const start = new Date(props.year, props.month - 1, startDay);
  const end   = new Date(props.year, props.month - 1, endDay);
  if (todayStart > end)   return 'done';
  if (todayStart < start) return 'upcoming';
  return 'current';
}

function weekdaysBetween(startDay, endDay) {
  let count = 0;
  for (let d = startDay; d <= endDay; d++) {
    const dow = new Date(props.year, props.month - 1, d).getDay();
    if (dow !== 0 && dow !== 6) count++;
  }
  return count;
}

// The most recent request for this exact span decides what the card says.
function consentFor(period) {
  if (!props.consents) return null;
  const c = props.consents.find(x =>
    x.month === props.month && x.year === props.year && (x.period ?? 'full') === period
  );
  if (!c) return null;
  if (c.status === 'pending')  return { tone: 'pending', label: 'Awaiting approval', icon: ClockIcon };
  if (c.status === 'denied')   return { tone: 'denied',  label: 'Request denied',    icon: XCircleIcon };
  if (c.used_at)               return { tone: 'used',    label: 'Downloaded',        icon: CheckCircle2Icon };
  return { tone: 'ready', label: 'Ready to download', icon: DownloadIcon };
}

const options = computed(() => {
  const m    = monthShort.value;
  const last = lastDay.value;
  return [
    { value: 'first',  title: '1st Half',    range: `${m} 1 – 15`,       start: 1,  end: 15 },
    { value: 'second', title: '2nd Half',    range: `${m} 16 – ${last}`, start: 16, end: last },
    { value: 'full',   title: 'Whole Month', range: `${m} 1 – ${last}`,  start: 1,  end: last },
  ].map(o => ({
    ...o,
    state:    stateOf(o.start, o.end),
    weekdays: weekdaysBetween(o.start, o.end),
    consent:  consentFor(o.value),
  }));
});

const selected = computed(() => options.value.find(o => o.value === props.modelValue) ?? options.value[0]);

// How much of the month has passed: all of a past month, none of a future one.
const elapsedPct = computed(() => {
  if (isCurrentMonth.value) return (today.getDate() / lastDay.value) * 100;
  return todayStart > new Date(props.year, props.month - 1, lastDay.value) ? 100 : 0;
});

const todayPct = computed(() =>
  isCurrentMonth.value ? ((today.getDate() - 0.5) / lastDay.value) * 100 : null
);

const todayLabel = today.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
</script>

<style scoped>
.dpp {
  --dpp-accent: #2E4A8C;
  --dpp-accent-soft: #EEF2FF;
  --dpp-border: #E2E8F0;
  --dpp-surface: #FFFFFF;
  --dpp-text: #0F172A;
  --dpp-muted: #6B7A94;
  --dpp-track: #E8EDF6;
}
[data-theme="dark"] .dpp {
  --dpp-accent: #60A5FA;
  --dpp-accent-soft: rgba(96, 165, 250, 0.12);
  --dpp-border: rgba(255, 255, 255, 0.10);
  --dpp-surface: #1C2A44;
  --dpp-text: #E4EAF5;
  --dpp-muted: #9AAAC9;
  --dpp-track: rgba(255, 255, 255, 0.08);
}

/* ── Header ── */
.dpp-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}
.dpp-label { font-size: 0.875rem; font-weight: 500; color: var(--dpp-text); }
.dpp-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  color: var(--dpp-accent);
  background: var(--dpp-accent-soft);
}

/* ── Cards ── */
.dpp-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.dpp-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  text-align: left;
  border-radius: 10px;
  border: 1.5px solid var(--dpp-border);
  background: var(--dpp-surface);
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, transform 0.18s ease;
}
.dpp-card:hover { border-color: color-mix(in srgb, var(--dpp-accent) 45%, var(--dpp-border)); transform: translateY(-1px); }
.dpp-card:active { transform: translateY(0); }
.dpp-card--active {
  border-color: var(--dpp-accent);
  background: var(--dpp-accent-soft);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--dpp-accent) 14%, transparent);
}
.dpp-check {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: var(--dpp-accent);
  opacity: 0;
  transform: scale(0.6);
  transition: opacity 0.18s ease, transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.dpp-card--active .dpp-check { opacity: 1; transform: scale(1); }

.dpp-card-title { font-size: 13px; font-weight: 700; color: var(--dpp-text); padding-right: 20px; }
.dpp-card--active .dpp-card-title { color: var(--dpp-accent); }
.dpp-card-range { font-size: 12px; color: var(--dpp-muted); }
.dpp-card-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
  margin-top: 4px;
}
.dpp-weekdays { font-size: 10.5px; color: var(--dpp-muted); }

.dpp-state {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  font-weight: 600;
}
.dpp-state-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.dpp-state--done     { color: #15803D; }
.dpp-state--current  { color: #2563EB; }
.dpp-state--current .dpp-state-dot { animation: dpp-pulse 1.8s ease-out infinite; }
.dpp-state--upcoming { color: #94A3B8; }
[data-theme="dark"] .dpp-state--done    { color: #4ADE80; }
[data-theme="dark"] .dpp-state--current { color: #60A5FA; }

@keyframes dpp-pulse {
  0%   { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 55%, transparent); }
  70%  { box-shadow: 0 0 0 5px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}

.dpp-consent {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 6px;
  padding: 2px 7px;
  border-radius: 6px;
  font-size: 10.5px;
  font-weight: 600;
}
.dpp-consent--ready   { color: #15803D; background: #DCFCE7; }
.dpp-consent--pending { color: #B45309; background: #FEF3C7; }
.dpp-consent--denied  { color: #B91C1C; background: #FEE2E2; }
.dpp-consent--used    { color: #475569; background: #F1F5F9; }
[data-theme="dark"] .dpp-consent--ready   { color: #4ADE80; background: rgba(74, 222, 128, 0.12); }
[data-theme="dark"] .dpp-consent--pending { color: #FBBF24; background: rgba(251, 191, 36, 0.12); }
[data-theme="dark"] .dpp-consent--denied  { color: #F87171; background: rgba(248, 113, 113, 0.12); }
[data-theme="dark"] .dpp-consent--used    { color: #9AAAC9; background: rgba(255, 255, 255, 0.06); }

/* ── Timeline ── */
.dpp-timeline { margin-top: 24px; }
.dpp-track {
  position: relative;
  display: flex;
  gap: 3px;
  height: 8px;
}
.dpp-seg {
  height: 100%;
  border-radius: 999px;
  background: var(--dpp-track);
  cursor: pointer;
  transition: background-color 0.25s ease;
}
.dpp-seg--on { background: color-mix(in srgb, var(--dpp-accent) 30%, var(--dpp-track)); }
.dpp-elapsed {
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, color-mix(in srgb, var(--dpp-accent) 55%, transparent), var(--dpp-accent));
  opacity: 0.55;
  pointer-events: none;
  transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1);
}
.dpp-today {
  position: absolute;
  top: -4px;
  width: 2px;
  height: 16px;
  margin-left: -1px;
  border-radius: 2px;
  background: var(--dpp-text);
  pointer-events: none;
}
.dpp-today-label {
  position: absolute;
  bottom: calc(100% + 3px);
  left: 50%;
  transform: translateX(-50%);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--dpp-text);
  white-space: nowrap;
}
.dpp-scale {
  position: relative;
  display: flex;
  justify-content: space-between;
  margin-top: 5px;
  font-size: 10.5px;
  color: var(--dpp-muted);
}
.dpp-scale-mid { position: absolute; transform: translateX(-50%); }

/* ── Notes ── */
.dpp-note {
  display: flex;
  gap: 6px;
  margin-top: 10px;
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.45;
}
.dpp-note--info { color: #1E40AF; background: #EFF6FF; }
.dpp-note--warn { color: #92400E; background: #FFFBEB; }
[data-theme="dark"] .dpp-note--info { color: #93C5FD; background: rgba(96, 165, 250, 0.10); }
[data-theme="dark"] .dpp-note--warn { color: #FCD34D; background: rgba(251, 191, 36, 0.10); }

.dpp-note-enter-active, .dpp-note-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.dpp-note-enter-from, .dpp-note-leave-to { opacity: 0; transform: translateY(-3px); }

/* ── Compact (Print Permission tab) ── */
.dpp--compact .dpp-card { padding: 8px 10px; }

/* Phones: stack the cards as rows so nothing gets squeezed */
@media (max-width: 520px) {
  .dpp-grid { grid-template-columns: 1fr; gap: 6px; }
  /* .dpp-grid prefix: outranks the compact variant's padding, so the
     checkmark never sits on top of the date range. */
  .dpp-grid .dpp-card {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    column-gap: 10px;
    row-gap: 2px;
    padding-right: 36px;
  }
  .dpp-card-title { padding-right: 0; }
  .dpp-card-range { text-align: right; }
  .dpp-card-foot, .dpp-consent { grid-column: 1 / -1; margin-top: 2px; }
  .dpp-check { top: 50%; margin-top: -9px; right: 10px; }
}

@media (prefers-reduced-motion: reduce) {
  .dpp-card, .dpp-check, .dpp-elapsed, .dpp-seg { transition: none; }
  .dpp-state--current .dpp-state-dot { animation: none; }
}
</style>
