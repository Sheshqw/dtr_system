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
          @click="openAdd"
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#0E1EC0] hover:bg-[#1228CC] transition-colors"
        >
          <PlusIcon class="w-4 h-4" />
          Add Holiday
        </button>
      </div>
    </PageHeader>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto bg-[#F4F6FB] p-6">

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-[#E2E8F0] p-4 animate-pulse">
          <div class="h-4 bg-slate-200 rounded w-24 mb-3"></div>
          <div class="space-y-2">
            <div v-for="j in 2" :key="j" class="h-10 bg-slate-100 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!loading && holidays.length === 0" class="text-center py-16">
        <CalendarIcon class="w-12 h-12 mx-auto text-[#CBD5E1] mb-3" />
        <p class="text-[#64748B] font-medium">No holidays in {{ year }}</p>
        <p class="text-sm text-[#94A3B8] mt-1">Add public or special holidays to automatically flag them in DTR records.</p>
        <button
          v-if="canManage"
          @click="openAdd"
          class="mt-4 px-4 py-2 text-sm font-medium text-white rounded-lg"
          style="background: #0E1EC0;"
        >
          Add First Holiday
        </button>
      </div>

      <!-- Legend + Count -->
      <div v-else class="mb-4 flex items-center gap-4 text-sm">
        <span class="text-[#64748B]">{{ holidays.length }} holiday{{ holidays.length !== 1 ? 's' : '' }} in {{ year }}</span>
        <span class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
          <span class="text-[#64748B]">Regular</span>
        </span>
        <span class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
          <span class="text-[#64748B]">Special</span>
        </span>
      </div>

      <!-- Grouped by month -->
      <div v-if="!loading && holidays.length > 0" class="space-y-4">
        <div
          v-for="(group, monthKey) in groupedHolidays"
          :key="monthKey"
          class="bg-white rounded-xl overflow-hidden holiday-month-card"
        >
          <!-- Month header -->
          <div class="px-5 py-3 flex items-center gap-2 holiday-month-head">
            <CalendarIcon class="w-4 h-4 text-[#94A3B8]" />
            <h3 class="text-sm font-semibold text-[#0F172A] uppercase tracking-wide">{{ monthKey }}</h3>
            <span class="ml-auto text-xs text-[#94A3B8]">{{ group.length }} day{{ group.length !== 1 ? 's' : '' }}</span>
          </div>

          <!-- Holidays in month -->
          <div class="divide-y divide-[#F1F5F9]">
            <div
              v-for="holiday in group"
              :key="holiday.id"
              class="holiday-row px-5 py-3.5 flex items-center gap-4 hover:bg-[#FAFBFF] transition-colors"
            >
              <!-- Date block -->
              <div class="flex-shrink-0 w-12 text-center">
                <p class="text-2xl font-bold text-[#0F172A] leading-none">{{ formatDay(holiday.date) }}</p>
                <p class="text-[10px] text-[#94A3B8] uppercase mt-0.5">{{ formatDayName(holiday.date) }}</p>
              </div>

              <!-- Vertical accent -->
              <div class="w-0.5 h-8 rounded-full flex-shrink-0" :class="holiday.type === 'regular' ? 'bg-red-500' : 'bg-amber-500'"></div>

              <!-- Name -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-[#0F172A]">{{ holiday.name }}</p>
                <p class="text-xs text-[#94A3B8] mt-0.5">{{ formatFullDate(holiday.date) }}</p>
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
                  class="p-1.5 rounded-md text-[#94A3B8] hover:text-[#0E1EC0] hover:bg-[#EEF2FF] transition-colors"
                  title="Edit"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="confirmDelete(holiday)"
                  class="p-1.5 rounded-md text-[#94A3B8] hover:text-red-600 hover:bg-red-50 transition-colors"
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
            <button @click="requestClose" class="text-[#94A3B8] hover:text-[#0F172A] transition-colors">
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
                class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#0E1EC0] hover:bg-[#1228CC] disabled:opacity-60 transition-colors"
              >
                {{ modal.saving ? 'Saving…' : (modal.editing ? 'Save Changes' : 'Add Holiday') }}
              </button>
            </div>
          </form>
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

const canManage = computed(() => auth.employee?.role === 'admin' || auth.employee?.role === 'hr_officer');

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

.modal-label {
  display: block; font-size: 13px; font-weight: 600; color: #1E293B; margin-bottom: 5px;
}
.modal-input {
  width: 100%; padding: 8px 12px; font-size: 13px;
  border: 1px solid #E4E9F2; border-radius: 8px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.modal-input:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

/* better month-group cards */
.holiday-month-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(8,14,128,0.05);
}
.holiday-month-head {
  padding: 10px 20px; border-bottom: 1px solid #EEF2FA;
  background: #F7F8FD; display: flex; align-items: center; gap: 8px;
}

@media (max-width: 640px) {
  /* A dedicated class, not an escaped Tailwind utility selector — the
     previous ".px-5.py-3\\.5" had a doubled backslash (an invalid CSS
     escape), so it silently never matched anything and tripped the
     build's CSS parser. */
  .holiday-row { padding: 10px 14px; }
}
</style>
