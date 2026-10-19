<template>
  <section class="mt" aria-labelledby="mt-title">

    <!-- ── Greeting + status ──────────────────────────────────────── -->
    <header class="mt-hero" :style="{ background: heroGradient }">
      <div class="mt-hero-left">
        <div class="mt-avatar" aria-hidden="true">{{ initials }}</div>
        <div class="min-w-0">
          <h2 id="mt-title" class="mt-title">{{ greeting }}, {{ firstName }}</h2>
          <p class="mt-sub">
            {{ longToday }}
            <template v-if="shift"> · {{ shift.name }} ({{ fmtTime(shift.morning_in) }} – {{ fmtTime(shift.afternoon_out) }})</template>
          </p>
        </div>
      </div>
      <StatusBadge :status="status" :holiday-name="row?.holiday_name" />
    </header>

    <div class="mt-body">

      <!-- ── Punch timeline ───────────────────────────────────────── -->
      <ol class="mt-timeline" aria-label="Today's punches">
        <li
          v-for="(step, i) in steps"
          :key="step.key"
          class="mt-step"
          :class="{ 'mt-step--done': step.time, 'mt-step--next': i === nextStep }"
        >
          <span class="mt-dot" aria-hidden="true">
            <CheckIcon v-if="step.time" class="w-3 h-3" />
          </span>
          <span class="mt-step-label">{{ step.label }}</span>
          <span class="mt-step-time">{{ step.time ? fmtTime(step.time) : '—' }}</span>
          <span v-if="step.key === 'morning_in' && row?.tardiness_min" class="mt-step-note mt-step-note--late">
            {{ row.tardiness_min }} min late
          </span>
          <span v-else-if="step.sched" class="mt-step-note">Sched {{ fmtTime(step.sched) }}</span>
        </li>
      </ol>

      <!-- ── Hours today + quick links ─────────────────────────────── -->
      <div class="mt-side">
        <div class="mt-duty">
          <template v-if="duty.mode === 'counting' || duty.mode === 'lunch' || duty.mode === 'done'">
            <p class="mt-duty-label">{{ duty.label }}</p>
            <p class="mt-duty-value">{{ fmtDuration(duty.minutes) }}</p>
            <div v-if="requiredMinutes" class="mt-bar" role="progressbar" :aria-valuenow="dutyPct" aria-valuemin="0" aria-valuemax="100">
              <span class="mt-bar-fill" :style="{ width: dutyPct + '%' }"></span>
            </div>
            <p class="mt-duty-note">
              <template v-if="requiredMinutes">{{ dutyPct }}% of your {{ shift.required_hours }} h day · </template>{{ duty.note }}
            </p>
          </template>
          <template v-else>
            <p class="mt-duty-label">Today</p>
            <p class="mt-duty-message">{{ duty.message }}</p>
          </template>
        </div>

        <div class="mt-actions">
          <button type="button" class="mt-action" @click="router.push({ name: 'reports' })">
            <FileTextIcon class="w-4 h-4" />
            <span>My DTR &amp; Form 48</span>
            <span v-if="pendingPrintRequests" class="mt-action-badge">{{ pendingPrintRequests }} pending</span>
          </button>
          <button type="button" class="mt-action" @click="router.push({ name: 'attendance' })">
            <ListIcon class="w-4 h-4" />
            <span>My punch logs</span>
          </button>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import StatusBadge from '@/components/StatusBadge.vue';
import {
  Check    as CheckIcon,
  FileText as FileTextIcon,
  List     as ListIcon,
} from 'lucide-vue-next';

const props = defineProps({
  // Today's row from /api/attendance/today (own record only for this user).
  row:                  { type: Object, default: null },
  // Shift from /api/me/attendance-summary; null until it loads or if unassigned.
  shift:                { type: Object, default: null },
  pendingPrintRequests: { type: Number, default: 0 },
  heroGradient:         { type: String, default: '#1B2A5E' },
});

const router = useRouter();
const auth   = useAuthStore();

// Ticks once a minute so the "on duty so far" figure stays current.
const nowMin = ref(minutesNow());
let timer = null;
function minutesNow() {
  const d = new Date();
  return d.getHours() * 60 + d.getMinutes();
}
onMounted(() => { timer = setInterval(() => { nowMin.value = minutesNow(); }, 30_000); });
onBeforeUnmount(() => clearInterval(timer));

const firstName = computed(() => (auth.employee?.name ?? '').split(' ')[0] || 'there');
const initials  = computed(() =>
  (auth.employee?.name ?? '').split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
);
const greeting = computed(() => {
  const h = Math.floor(nowMin.value / 60);
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});
const longToday = new Date().toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

const status = computed(() => props.row?.status ?? 'absent');

const steps = computed(() => [
  { key: 'morning_in',    label: 'Time in',   time: props.row?.morning_in,    sched: props.shift?.morning_in },
  { key: 'morning_out',   label: 'Lunch out', time: props.row?.morning_out,   sched: props.shift?.morning_out },
  { key: 'afternoon_in',  label: 'Lunch in',  time: props.row?.afternoon_in,  sched: props.shift?.afternoon_in },
  { key: 'afternoon_out', label: 'Time out',  time: props.row?.afternoon_out, sched: props.shift?.afternoon_out },
]);
// The first missing punch after the last recorded one — highlighted as "next".
const nextStep = computed(() => {
  if (['weekend', 'holiday', 'on_leave'].includes(status.value)) return -1;
  const lastDone = steps.value.map(s => !!s.time).lastIndexOf(true);
  return lastDone < steps.value.length - 1 ? lastDone + 1 : -1;
});

// Time on duty so far, from the punches themselves. Once the day is
// closed, the system's own computed hours take over.
const duty = computed(() => {
  const r = props.row;
  const s = status.value;
  if (s === 'weekend')  return { mode: 'message', message: 'It\'s the weekend. Enjoy your rest day.' };
  if (s === 'holiday')  return { mode: 'message', message: `Holiday${r?.holiday_name ? `: ${r.holiday_name}` : ''}. No time-in needed today.` };
  if (s === 'on_leave') return { mode: 'message', message: 'You\'re marked on leave today.' };
  if (!r?.morning_in)   return { mode: 'message', message: 'No time-in recorded yet today. Punch in on the biometric device when you arrive.' };

  if (r.afternoon_out && Number(r.hours_rendered) > 0) {
    return { mode: 'done', label: 'Hours rendered today', minutes: Math.round(Number(r.hours_rendered) * 60), note: 'Computed by the system.' };
  }

  const inMin   = toMinutes(r.morning_in);
  const endMin  = r.afternoon_out ? toMinutes(r.afternoon_out) : nowMin.value;
  const onLunch = !!r.morning_out && !r.afternoon_in;
  let minutes;
  if (r.morning_out && r.afternoon_in) {
    // Both lunch punches: morning block + afternoon block.
    minutes = Math.max(0, toMinutes(r.morning_out) - inMin) + Math.max(0, endMin - toMinutes(r.afternoon_in));
  } else if (onLunch) {
    minutes = toMinutes(r.morning_out) - inMin;
  } else {
    // No lunch punches (or only one): one continuous span.
    minutes = endMin - inMin;
  }
  minutes = Math.max(0, minutes);
  return {
    mode:  onLunch ? 'lunch' : 'counting',
    label: onLunch ? 'On lunch break · worked so far' : 'On duty so far',
    minutes,
    note:  'Estimate from your punches; official hours are computed after time-out.',
  };
});

const requiredMinutes = computed(() => (props.shift?.required_hours ? Number(props.shift.required_hours) * 60 : 0));
const dutyPct = computed(() =>
  requiredMinutes.value ? Math.min(100, Math.round(duty.value.minutes / requiredMinutes.value * 100)) : 0
);

function toMinutes(t) {
  const [h, m] = String(t).split(':').map(Number);
  return h * 60 + (m || 0);
}
function fmtTime(t) {
  if (!t) return '—';
  const total = toMinutes(t);
  const h = Math.floor(total / 60), m = total % 60;
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
}
function fmtDuration(min) {
  const h = Math.floor(min / 60), m = min % 60;
  return h ? `${h} h ${String(m).padStart(2, '0')} min` : `${m} min`;
}
</script>

<style scoped>
.mt {
  --mt-card:   #fff;
  --mt-border: #E4E9F2;
  --mt-soft:   #F7F9FC;
  --mt-ink:    #0F172A;
  --mt-muted:  #6B7A94;
  --mt-faint:  #CBD5E1;
  --mt-accent: #3D5CA3;

  background: var(--mt-card);
  border: 1px solid var(--mt-border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 32px -8px rgba(27, 42, 94, 0.13), 0 1px 4px rgba(27, 42, 94, 0.05);
}
:global([data-theme="dark"] .mt) {
  --mt-card:   #1C2A44;
  --mt-border: rgba(255, 255, 255, 0.08);
  --mt-soft:   #17223A;
  --mt-ink:    #E4EAF5;
  --mt-muted:  #9AAAC9;
  --mt-faint:  #475569;
  --mt-accent: #7C9BE0;
}

/* ── Header ───────────────────────────────────────────────────── */
.mt-hero {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
  padding: 16px 20px;
}
.mt-hero-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.mt-avatar {
  width: 40px; height: 40px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 999px;
  font-size: 13px; font-weight: 800; color: #fff;
  background: linear-gradient(135deg, #C9A227, #B8911F);
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
}
.mt-title { font-size: 16px; font-weight: 700; color: #fff; line-height: 1.2; }
.mt-sub   { font-size: 12px; color: rgba(255, 255, 255, 0.65); margin-top: 3px; }

/* ── Body ─────────────────────────────────────────────────────── */
.mt-body {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
  gap: 20px;
  padding: 20px;
}

/* ── Timeline ─────────────────────────────────────────────────── */
.mt-timeline {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-self: center;
}
.mt-step {
  position: relative;
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  text-align: center;
  padding: 0 4px;
}
/* connector to the next step */
.mt-step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 11px; left: calc(50% + 14px); right: calc(-50% + 14px);
  height: 2px;
  background: var(--mt-border);
}
.mt-step--done:not(:last-child)::after { background: #16A34A; }
.mt-dot {
  width: 24px; height: 24px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 999px;
  border: 2px solid var(--mt-faint);
  background: var(--mt-card);
  color: #fff;
  margin-bottom: 4px;
}
.mt-step--done .mt-dot { background: #16A34A; border-color: #16A34A; }
.mt-step--next .mt-dot { border-color: var(--mt-accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--mt-accent) 18%, transparent); }
.mt-step-label { font-size: 10.5px; font-weight: 700; color: var(--mt-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.mt-step-time  { font-size: 15px; font-weight: 800; color: var(--mt-ink); font-variant-numeric: tabular-nums; }
.mt-step:not(.mt-step--done) .mt-step-time { color: var(--mt-faint); }
.mt-step-note  { font-size: 10.5px; color: var(--mt-muted); }
.mt-step-note--late { color: #B45309; font-weight: 700; }
:global([data-theme="dark"] .mt-step-note--late) { color: #FCD34D; }

/* ── Side: hours + actions ────────────────────────────────────── */
.mt-side { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.mt-duty {
  padding: 14px;
  border-radius: 12px;
  background: var(--mt-soft);
  border: 1px solid var(--mt-border);
}
.mt-duty-label   { font-size: 10.5px; font-weight: 700; color: var(--mt-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.mt-duty-value   { font-size: 22px; font-weight: 800; color: var(--mt-ink); margin-top: 2px; font-variant-numeric: tabular-nums; }
.mt-duty-message { font-size: 13px; font-weight: 600; color: var(--mt-ink); margin-top: 4px; line-height: 1.4; }
.mt-duty-note    { font-size: 11px; color: var(--mt-muted); margin-top: 6px; line-height: 1.4; }
.mt-bar {
  height: 8px; margin-top: 8px;
  border-radius: 999px;
  background: var(--mt-card);
  border: 1px solid var(--mt-border);
  overflow: hidden;
}
.mt-bar-fill {
  display: block; height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #22C55E, #15803D);
  transition: width 0.6s ease;
}

.mt-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.mt-action {
  position: relative;
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 12.5px; font-weight: 600;
  color: var(--mt-ink);
  background: var(--mt-card);
  border: 1px solid var(--mt-border);
  text-align: left;
  transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.mt-action:hover { border-color: var(--mt-accent); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); }
.mt-action svg { color: var(--mt-accent); flex-shrink: 0; }
.mt-action-badge {
  margin-left: auto;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 10.5px; font-weight: 700;
  background: #EEF2FF; color: #3730A3;
}

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 900px) {
  .mt-body { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
  .mt-hero { padding: 14px; }
  .mt-body { padding: 14px; gap: 14px; }
  .mt-step-time { font-size: 13px; }
  .mt-step-label, .mt-step-note { font-size: 9.5px; }
  .mt-actions { grid-template-columns: minmax(0, 1fr); }
}
</style>
