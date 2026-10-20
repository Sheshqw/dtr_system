<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Holiday Calendar" subtitle="Manage public holidays — auto-applied to attendance computation" :icon="CalendarIcon">
      <div class="flex items-center gap-3">
        <!-- Year navigation -->
        <div class="flex items-center gap-1 rounded-lg overflow-hidden" style="border:1px solid rgba(255,255,255,0.18); background:rgba(255,255,255,0.07);">
          <button @click="prevYear" class="px-3 py-2 text-white/70 hover:bg-white/10 transition-colors">
            <ChevronLeftIcon class="w-4 h-4" />
          </button>
          <span class="px-4 text-sm font-semibold text-white min-w-[4rem] text-center">{{ year }}</span>
          <button @click="nextYear" class="px-3 py-2 text-white/70 hover:bg-white/10 transition-colors">
            <ChevronRightIcon class="w-4 h-4" />
          </button>
        </div>
        <button
          v-if="canManage"
          @click="autofillHolidays"
          :disabled="autofilling"
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-white/10 border border-white/20 hover:bg-white/20 transition-colors disabled:opacity-60"
        >
          <SparklesIcon class="w-4 h-4" :class="autofilling ? 'animate-pulse' : ''" />
          {{ autofilling ? 'Adding…' : 'Auto-fill PH Holidays' }}
        </button>
        <button
          v-if="canManage"
          @click="openAdd"
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#2E4A8C] hover:bg-[#3D5CA3] transition-colors"
        >
          <PlusIcon class="w-4 h-4" />
          Add Holiday
        </button>
      </div>
    </PageHeader>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto bg-[#F4F6FB] p-6">

      <!-- Stats -->
      <div v-if="!loading" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="stat-tile" :style="statVars('#2E4A8C')">
          <div class="stat-tile-icon"><CalendarIcon class="w-5 h-5" /></div>
          <div>
            <p class="stat-tile-value">{{ holidays.length }}</p>
            <p class="stat-tile-label">Total in {{ year }}</p>
          </div>
        </div>
        <div class="stat-tile" :style="statVars('#DC2626')">
          <div class="stat-tile-icon"><FlagIcon class="w-5 h-5" /></div>
          <div>
            <p class="stat-tile-value">{{ regularCount }}</p>
            <p class="stat-tile-label">Regular</p>
          </div>
        </div>
        <div class="stat-tile" :style="statVars('#D97706')">
          <div class="stat-tile-icon"><StarIcon class="w-5 h-5" /></div>
          <div>
            <p class="stat-tile-value">{{ specialCount }}</p>
            <p class="stat-tile-label">Special</p>
          </div>
        </div>
        <div class="stat-tile" :style="statVars('#7C3AED')">
          <div class="stat-tile-icon"><CalendarClockIcon class="w-5 h-5" /></div>
          <div class="min-w-0">
            <p class="stat-tile-value truncate" :title="nextHoliday?.name ?? '—'">{{ nextHoliday?.name ?? '—' }}</p>
            <p class="stat-tile-label">{{ nextHolidayLabel }}</p>
          </div>
        </div>
      </div>

      <!-- View toggle -->
      <div class="flex items-center gap-2 mb-4">
        <button
          @click="view = 'calendar'"
          class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
          :class="view === 'calendar' ? 'bg-[#2E4A8C] text-white' : 'bg-white border border-[#E2E8F0] text-[#64748B] hover:bg-[#F8FAFC]'"
        >
          <CalendarDaysIcon class="w-4 h-4 inline -mt-0.5 mr-1" /> Calendar
        </button>
        <button
          @click="view = 'list'"
          class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors"
          :class="view === 'list' ? 'bg-[#2E4A8C] text-white' : 'bg-white border border-[#E2E8F0] text-[#64748B] hover:bg-[#F8FAFC]'"
        >
          <ListIcon class="w-4 h-4 inline -mt-0.5 mr-1" /> List
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-[#E2E8F0] p-4 animate-pulse">
          <div class="h-4 bg-slate-200 rounded w-24 mb-3"></div>
          <div class="space-y-2">
            <div v-for="j in 2" :key="j" class="h-10 bg-slate-100 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Calendar grid view -->
      <div v-else-if="view === 'calendar'" class="cal-grid">
        <div v-for="m in monthGrids" :key="m.month" class="cal-month" :class="{ 'cal-month--current': m.isCurrent }">
          <h3 class="cal-month-title">{{ m.name }}</h3>
          <div class="cal-dow-row">
            <span v-for="(d, di) in ['S','M','T','W','T','F','S']" :key="di" class="cal-dow">{{ d }}</span>
          </div>
          <div class="cal-days">
            <button
              v-for="(day, i) in m.days" :key="i"
              type="button"
              class="cal-day"
              :class="[
                day?.holiday ? (day.holiday.type === 'regular' ? 'cal-day--regular' : 'cal-day--special') : '',
                day?.isToday ? 'cal-day--today' : '',
                !day ? 'cal-day--blank' : '',
              ]"
              :disabled="!day"
              @click="day && onDayClick(day)"
            >{{ day?.num ?? '' }}</button>
          </div>

          <!-- Holiday list for this month — always visible, not hidden
               behind hover/tap-only tooltips (which don't work on mobile). -->
          <div v-if="m.holidays.length" class="cal-month-list">
            <button
              v-for="h in m.holidays" :key="h.id"
              type="button"
              class="cal-month-list-item"
              @click="onDayClick({ holiday: h })"
            >
              <span class="cal-month-list-dot" :class="h.type === 'regular' ? 'bg-red-500' : 'bg-amber-500'"></span>
              <span class="cal-month-list-day">{{ formatDay(h.date) }}</span>
              <span class="cal-month-list-name">{{ h.name }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading && holidays.length === 0 && view === 'list'" class="text-center py-16">
        <CalendarIcon class="w-12 h-12 mx-auto text-[#94A3B8] mb-3" />
        <p class="text-[#64748B] font-medium">No holidays in {{ year }}</p>
        <p class="text-sm text-[#6B7A94] mt-1">Add public or special holidays to automatically flag them in DTR records.</p>
        <button
          v-if="canManage"
          @click="openAdd"
          class="mt-4 px-4 py-2 text-sm font-medium text-white rounded-lg"
          style="background: #2E4A8C;"
        >
          Add First Holiday
        </button>
      </div>


      <!-- Grouped by month -->
      <div v-if="!loading && view === 'list' && holidays.length > 0" class="space-y-4">
        <div
          v-for="(group, monthKey) in groupedHolidays"
          :key="monthKey"
          class="bg-white rounded-xl overflow-hidden holiday-month-card"
        >
          <!-- Month header -->
          <div class="px-5 py-3 flex items-center gap-2 holiday-month-head">
            <CalendarIcon class="w-4 h-4 text-[#6B7A94]" />
            <h3 class="text-sm font-semibold text-[#0F172A] uppercase tracking-wide">{{ monthKey }}</h3>
            <span class="ml-auto text-xs text-[#6B7A94]">{{ group.length }} day{{ group.length !== 1 ? 's' : '' }}</span>
          </div>

          <!-- Holidays in month -->
          <div class="divide-y divide-[#F1F5F9]">
            <div
              v-for="holiday in group"
              :key="holiday.id"
              class="holiday-row px-5 py-3.5 flex items-center gap-4 hover:bg-[#FAFBFF] transition-colors"
            >
              <!-- Date tile -->
              <div class="holiday-date-tile" :class="holiday.type === 'regular' ? 'holiday-date-tile--regular' : 'holiday-date-tile--special'">
                <p class="holiday-date-day">{{ formatDay(holiday.date) }}</p>
                <p class="holiday-date-dow">{{ formatDayName(holiday.date) }}</p>
              </div>

              <!-- Name -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-[#0F172A]">{{ holiday.name }}</p>
                <p class="text-xs text-[#6B7A94] mt-0.5">{{ formatFullDate(holiday.date) }}</p>
              </div>

              <!-- Type badge -->
              <span
                class="flex-shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full"
                :class="holiday.type === 'regular'
                  ? 'bg-red-100 text-red-700'
                  : 'bg-amber-100 text-amber-700'"
              >
                {{ holiday.type === 'regular' ? 'Regular' : 'Special' }}
              </span>

              <!-- Actions (admin/HR only) -->
              <div v-if="canManage" class="flex items-center gap-1 flex-shrink-0">
                <button
                  @click="openEdit(holiday)"
                  class="p-1.5 rounded-md text-[#6B7A94] hover:text-[#2E4A8C] hover:bg-[#EEF2FF] transition-colors"
                  title="Edit"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="confirmDelete(holiday)"
                  class="p-1.5 rounded-md text-[#6B7A94] hover:text-red-600 hover:bg-red-50 transition-colors"
                  title="Delete"
                >
                  <Trash2Icon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="requestClose"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <!-- Modal header -->
          <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#0F172A]">{{ modal.editing ? 'Edit Holiday' : 'Add Holiday' }}</h2>
            <button @click="requestClose" class="text-[#6B7A94] hover:text-[#0F172A] transition-colors">
              <XIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Form -->
          <form @submit.prevent="saveHoliday" class="px-6 py-5 space-y-4">
            <div>
              <label class="modal-label">Holiday Name</label>
              <input
                v-model="form.name"
                required
                placeholder="e.g. New Year's Day"
                class="modal-input"
              />
            </div>
            <div>
              <label class="modal-label">Date</label>
              <input
                v-model="form.date"
                type="date"
                required
                class="modal-input"
              />
            </div>
            <div>
              <label class="modal-label">Holiday Type</label>
              <div class="flex gap-3 mt-1">
                <label class="flex-1 flex items-center gap-2 p-3 border rounded-lg cursor-pointer transition-colors"
                  :class="form.type === 'regular' ? 'border-red-400 bg-red-50' : 'border-[#E2E8F0] hover:bg-[#F8FAFC]'"
                >
                  <input v-model="form.type" type="radio" value="regular" class="sr-only" />
                  <span class="w-3 h-3 rounded-full flex-shrink-0" :class="form.type === 'regular' ? 'bg-red-500' : 'bg-[#CBD5E1]'"></span>
                  <span class="text-sm font-medium text-[#0F172A]">Regular Holiday</span>
                </label>
                <label class="flex-1 flex items-center gap-2 p-3 border rounded-lg cursor-pointer transition-colors"
                  :class="form.type === 'special' ? 'border-amber-400 bg-amber-50' : 'border-[#E2E8F0] hover:bg-[#F8FAFC]'"
                >
                  <input v-model="form.type" type="radio" value="special" class="sr-only" />
                  <span class="w-3 h-3 rounded-full flex-shrink-0" :class="form.type === 'special' ? 'bg-amber-500' : 'bg-[#CBD5E1]'"></span>
                  <span class="text-sm font-medium text-[#0F172A]">Special Holiday</span>
                </label>
              </div>
            </div>

            <!-- Error -->
            <p v-if="modal.error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
              {{ modal.error }}
            </p>

            <div class="flex justify-end gap-3 pt-1">
              <button type="button" @click="requestClose" class="px-4 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
                Cancel
              </button>
              <button
                type="submit"
                :disabled="modal.saving"
                class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#2E4A8C] hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors"
              >
                {{ modal.saving ? 'Saving…' : (modal.editing ? 'Save Changes' : 'Add Holiday') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Day detail popover (view/edit/add from the calendar) -->
    <Teleport to="body">
      <div v-if="dayPopover" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="dayPopover = null"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5">
          <template v-if="dayPopover.holiday">
            <div class="flex items-center gap-3 mb-3">
              <span class="w-3 h-3 rounded-full flex-shrink-0" :class="dayPopover.holiday.type === 'regular' ? 'bg-red-500' : 'bg-amber-500'"></span>
              <div>
                <p class="text-sm font-semibold text-[#0F172A]">{{ dayPopover.holiday.name }}</p>
                <p class="text-xs text-[#6B7A94]">{{ formatFullDate(dayPopover.holiday.date) }} — {{ dayPopover.holiday.type === 'regular' ? 'Regular Holiday' : 'Special Holiday' }}</p>
              </div>
            </div>
            <div v-if="canManage" class="flex gap-2 pt-2">
              <button @click="dayPopover = null; openEdit(dayPopover.holiday)" class="flex-1 py-2 text-sm font-medium rounded-lg border border-[#E2E8F0] text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
                Edit
              </button>
              <button @click="dayPopover = null" class="flex-1 py-2 text-sm font-medium rounded-lg bg-[#F1F5F9] text-[#374151] hover:bg-[#E2E8F0] transition-colors">
                Close
              </button>
            </div>
            <button v-else @click="dayPopover = null" class="w-full mt-2 py-2 text-sm font-medium rounded-lg border border-[#E2E8F0] text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
              Close
            </button>
          </template>
          <template v-else>
            <p class="text-sm font-semibold text-[#0F172A] mb-1">{{ formatFullDate(dayPopover.date) }}</p>
            <p class="text-xs text-[#6B7A94] mb-3">Not a holiday.</p>
            <div v-if="canManage" class="flex gap-2">
              <button @click="dayPopover = null" class="flex-1 py-2 text-sm font-medium rounded-lg border border-[#E2E8F0] text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
                Cancel
              </button>
              <button @click="openAddOnDate(dayPopover.date)" class="flex-1 py-2 text-sm font-medium rounded-lg text-white bg-[#2E4A8C] hover:bg-[#3D5CA3] transition-colors">
                Add Holiday
              </button>
            </div>
            <button v-else @click="dayPopover = null" class="w-full py-2 text-sm font-medium rounded-lg border border-[#E2E8F0] text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
              Close
            </button>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirmation -->
    <Teleport to="body">
      <div v-if="delTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="delTarget = null"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
          <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
            <Trash2Icon class="w-5 h-5 text-red-600" />
          </div>
          <h3 class="text-base font-semibold text-[#0F172A] text-center">Delete Holiday?</h3>
          <p class="text-sm text-[#64748B] text-center mt-1">
            <span class="font-medium text-[#0F172A]">{{ delTarget?.name }}</span> will be removed.
            Past DTR records already computed won't change.
          </p>
          <div class="flex gap-3 mt-5">
            <button @click="delTarget = null" class="flex-1 py-2 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">Cancel</button>
            <button @click="doDelete" :disabled="deleting" class="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium disabled:opacity-60 transition-colors">
              {{ deleting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import PageHeader from '@/components/PageHeader.vue';
import {
  Calendar as CalendarIcon,
  CalendarDays as CalendarDaysIcon,
  CalendarClock as CalendarClockIcon,
  Flag as FlagIcon,
  Star as StarIcon,
  List as ListIcon,
  Sparkles as SparklesIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon,
  Plus as PlusIcon,
  Pencil as PencilIcon,
  Trash2 as Trash2Icon,
  X as XIcon,
} from 'lucide-vue-next';

const auth    = useAuthStore();
const { toast } = useToast();
const unsavedGuard = useUnsavedGuard();

const year     = ref(new Date().getFullYear());
const holidays = ref([]);
const loading  = ref(false);
const delTarget = ref(null);
const deleting  = ref(false);
const view       = ref('calendar');
const autofilling = ref(false);

const canManage = computed(() => auth.isPrivileged);

const modal = ref({ open: false, editing: null, saving: false, error: '' });
const form  = ref({ name: '', date: '', type: 'regular' });

const groupedHolidays = computed(() => {
  const groups = {};
  for (const h of holidays.value) {
    const key = new Date(h.date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long' });
    if (!groups[key]) groups[key] = [];
    groups[key].push(h);
  }
  return groups;
});

function statVars(hex) {
  return { '--stat-color': hex, '--stat-bg': hex + '14', '--stat-glow': hex + '30' };
}

const regularCount = computed(() => holidays.value.filter(h => h.type === 'regular').length);
const specialCount = computed(() => holidays.value.filter(h => h.type === 'special').length);

const nextHoliday = computed(() => {
  if (!holidays.value.length) return null;
  const todayStr = new Date().toISOString().slice(0, 10);
  const upcoming = holidays.value.filter(h => h.date >= todayStr).sort((a, b) => a.date.localeCompare(b.date));
  return upcoming[0] ?? holidays.value[0];
});

const nextHolidayLabel = computed(() => {
  if (!nextHoliday.value) return 'No holidays yet';
  const todayStr = new Date().toISOString().slice(0, 10);
  if (nextHoliday.value.date < todayStr) return `First in ${year.value}`;
  const days = Math.round((new Date(nextHoliday.value.date) - new Date(todayStr)) / 86400000);
  if (days === 0) return 'Today';
  if (days === 1) return 'Tomorrow';
  return `In ${days} days`;
});

const monthGrids = computed(() => {
  const byDate = {};
  for (const h of holidays.value) byDate[h.date] = h;

  const todayStr = new Date().toISOString().slice(0, 10);
  const months = [];

  for (let month = 0; month < 12; month++) {
    const firstOfMonth = new Date(year.value, month, 1);
    const daysInMonth   = new Date(year.value, month + 1, 0).getDate();
    const leadBlanks     = firstOfMonth.getDay(); // 0=Sun

    const days = [];
    for (let i = 0; i < leadBlanks; i++) days.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
      const iso = `${year.value}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      days.push({ date: iso, num: d, holiday: byDate[iso] ?? null, isToday: iso === todayStr });
    }

    const monthHolidays = holidays.value
      .filter(h => h.date.slice(5, 7) === String(month + 1).padStart(2, '0'))
      .slice()
      .sort((a, b) => a.date.localeCompare(b.date));

    months.push({
      month,
      name: firstOfMonth.toLocaleDateString('en-PH', { month: 'long' }),
      isCurrent: year.value === new Date().getFullYear() && month === new Date().getMonth(),
      days,
      holidays: monthHolidays,
    });
  }
  return months;
});

const dayPopover = ref(null);

function onDayClick(day) {
  dayPopover.value = day;
}

function openAddOnDate(date) {
  dayPopover.value = null;
  form.value = { name: '', date, type: 'regular' };
  modal.value = { open: true, editing: null, saving: false, error: '' };
  unsavedGuard.snapshot(form.value);
}

async function autofillHolidays() {
  autofilling.value = true;
  try {
    const res = await axios.post('/api/holidays/autofill', { year: year.value });
    toast(res.data.message, 'success');
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to auto-fill holidays.', 'error');
  } finally {
    autofilling.value = false;
  }
}

function formatDay(date) {
  return new Date(date + 'T00:00:00').getDate();
}

function formatDayName(date) {
  return new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short' });
}

function formatFullDate(date) {
  return new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

async function load() {
  loading.value = true;
  try {
    const res = await axios.get('/api/holidays', { params: { year: year.value } });
    holidays.value = res.data;
  } finally {
    loading.value = false;
  }
}

function prevYear() { year.value--; load(); }
function nextYear() { year.value++; load(); }

function openAdd() {
  form.value = { name: '', date: `${year.value}-01-01`, type: 'regular' };
  modal.value = { open: true, editing: null, saving: false, error: '' };
  unsavedGuard.snapshot(form.value);
}

function openEdit(holiday) {
  form.value = { name: holiday.name, date: holiday.date, type: holiday.type };
  modal.value = { open: true, editing: holiday, saving: false, error: '' };
  unsavedGuard.snapshot(form.value);
}

function closeModal() {
  modal.value.open = false;
}

// Backdrop click / X / Cancel go through here — warns before discarding a
// dirty form. The programmatic close after a successful save (below) skips
// this and calls closeModal() directly.
async function requestClose() {
  await unsavedGuard.close(form.value, closeModal);
}

async function saveHoliday() {
  modal.value.saving = true;
  modal.value.error  = '';
  try {
    if (modal.value.editing) {
      const res = await axios.put(`/api/holidays/${modal.value.editing.id}`, form.value);
      const idx = holidays.value.findIndex(h => h.id === modal.value.editing.id);
      if (idx !== -1) holidays.value[idx] = res.data;
      toast('Holiday updated successfully.', 'success');
    } else {
      const res = await axios.post('/api/holidays', form.value);
      holidays.value.push(res.data);
      holidays.value.sort((a, b) => a.date.localeCompare(b.date));
      toast('Holiday added successfully.', 'success');
    }
    closeModal();
  } catch (e) {
    modal.value.error = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.';
  } finally {
    modal.value.saving = false;
  }
}

function confirmDelete(holiday) {
  delTarget.value = holiday;
}

async function doDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/api/holidays/${delTarget.value.id}`);
    holidays.value = holidays.value.filter(h => h.id !== delTarget.value.id);
    toast('Holiday deleted.', 'warning');
    delTarget.value = null;
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
@reference "../css/app.css";

/* ── Year calendar grid ──────────────────────────────────────────── */
.cal-grid {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
}
@media (max-width: 1024px) { .cal-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  { .cal-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .cal-grid { grid-template-columns: 1fr; } }

.cal-month {
  background: #fff; border: 1px solid #E4E9F2; border-top: 3px solid #C9A227; border-radius: 10px;
  padding: 12px; box-shadow: 0 2px 8px rgba(27, 42, 94,0.05);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cal-month:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(27, 42, 94,0.09); }
.cal-month--current { border-top-color: #2E4A8C; background: linear-gradient(180deg, rgba(46,74,140,0.035), #fff 60px); }
.cal-month-title { font-size: 13px; font-weight: 700; color: #0F172A; margin: 0 0 8px; text-align: center; }
.cal-dow-row { display: grid; grid-template-columns: repeat(7, 1fr); margin-bottom: 4px; }
.cal-dow { font-size: 9px; font-weight: 700; color: #94A3B8; text-align: center; text-transform: uppercase; }
.cal-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.cal-day {
  aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: #475569; border-radius: 6px;
  border: none; background: transparent; font-family: inherit; cursor: pointer;
  transition: transform 0.1s ease, background 0.15s ease;
}
.cal-day:hover:not(:disabled) { background: #F1F5F9; }
.cal-day:active:not(:disabled) { transform: scale(0.92); }
.cal-day:disabled { cursor: default; }
.cal-day--blank { visibility: hidden; }
.cal-day--today    { box-shadow: inset 0 0 0 1.5px #2E4A8C; font-weight: 700; color: #2E4A8C; }
.cal-day--regular  { background: rgba(239,68,68,0.14); color: #DC2626; font-weight: 700; }
.cal-day--special  { background: rgba(245,158,11,0.16); color: #B45309; font-weight: 700; }
.cal-day--regular:hover  { background: rgba(239,68,68,0.24); }
.cal-day--special:hover  { background: rgba(245,158,11,0.26); }

/* ── Per-month holiday list — always visible, no hover/tap-only info ── */
.cal-month-list {
  margin-top: 10px; padding-top: 8px; border-top: 1px solid #F1F5F9;
  display: flex; flex-direction: column; gap: 3px;
}
.cal-month-list-item {
  display: flex; align-items: center; gap: 6px; padding: 3px 4px;
  border: none; background: transparent; border-radius: 6px; text-align: left;
  font-family: inherit; cursor: pointer; transition: background 0.15s ease;
}
.cal-month-list-item:hover { background: #F8FAFC; }
.cal-month-list-dot { width: 7px; height: 7px; border-radius: 999px; flex-shrink: 0; }
.cal-month-list-day { font-size: 10.5px; font-weight: 700; color: #64748B; font-variant-numeric: tabular-nums; width: 16px; flex-shrink: 0; }
.cal-month-list-name { font-size: 11px; color: #374151; line-height: 1.3; }

.modal-label {
  display: block; font-size: 13px; font-weight: 600; color: #1E293B; margin-bottom: 5px;
}
.modal-input {
  width: 100%; padding: 8px 12px; font-size: 13px;
  border: 1px solid #E4E9F2; border-radius: 8px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.modal-input:focus { outline: none; border-color: #2E4A8C; box-shadow: 0 0 0 3px rgba(46, 74, 140,0.08); }

/* better month-group cards */
.holiday-month-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(27, 42, 94,0.05);
}
.holiday-month-head {
  padding: 10px 20px; border-bottom: 1px solid #EEF2FA;
  background: #F7F8FD; display: flex; align-items: center; gap: 8px;
}

/* ── Date tile — reads like a small calendar cell rather than floating
     text, and its tint communicates Regular/Special before you even
     reach the text badge on the right. ─────────────────────────────── */
.holiday-date-tile {
  flex-shrink: 0; width: 52px; text-align: center;
  border-radius: 10px; padding: 6px 4px 7px; border: 1px solid;
  transition: transform 0.15s ease;
}
.holiday-row:hover .holiday-date-tile { transform: translateY(-1px); }
.holiday-date-tile--regular { background: rgba(239,68,68,0.07); border-color: rgba(239,68,68,0.20); }
.holiday-date-tile--special { background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.22); }
.holiday-date-day { font-size: 20px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
.holiday-date-tile--regular .holiday-date-day { color: #DC2626; }
.holiday-date-tile--special .holiday-date-day { color: #D97706; }
.holiday-date-dow { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #94A3B8; margin-top: 3px; }

@media (max-width: 640px) {
  /* A dedicated class, not an escaped Tailwind utility selector — the
     previous ".px-5.py-3\\.5" had a doubled backslash (an invalid CSS
     escape), so it silently never matched anything and tripped the
     build's CSS parser. */
  .holiday-row { padding: 10px 14px; }
}
</style>
