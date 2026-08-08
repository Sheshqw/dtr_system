<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Shift Management" subtitle="Configure work schedules — used for tardiness and undertime computation" :icon="TimerIcon">
      <button @click="openAdd" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors ph-btn-primary">
        <PlusIcon class="w-4 h-4" />
        Add Shift
      </button>
    </PageHeader>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto bg-[#F4F6FB] p-6">

      <!-- Loading skeleton -->
      <div v-if="loading" class="bg-white rounded-xl border border-[#E2E8F0] overflow-hidden">
        <div v-for="i in 3" :key="i" class="px-5 py-4 border-b border-[#F1F5F9] animate-pulse flex gap-4">
          <div class="h-4 bg-slate-200 rounded w-32"></div>
          <div class="h-4 bg-slate-100 rounded w-24 ml-auto"></div>
        </div>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl overflow-hidden shift-table-card">
        <table class="w-full text-sm">
          <thead class="border-b border-[#EEF2FA] bg-[#F7F8FD]">
            <tr>
              <th class="text-left px-5 py-3 font-bold text-[#5A6899] uppercase text-[10.5px] tracking-widest">Shift Name</th>
              <th class="text-center px-4 py-3 font-bold text-[#5A6899] uppercase text-[10.5px] tracking-widest">Morning</th>
              <th class="text-center px-4 py-3 font-bold text-[#5A6899] uppercase text-[10.5px] tracking-widest">Afternoon</th>
              <th class="text-center px-4 py-3 font-bold text-[#5A6899] uppercase text-[10.5px] tracking-widest">Grace</th>
              <th class="text-center px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Req. Hours</th>
              <th class="text-center px-4 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Employees</th>
              <th class="text-right px-5 py-3 font-semibold text-[#64748B] uppercase text-xs tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="shifts.length === 0">
              <td colspan="7" class="px-5 py-12 text-center text-[#94A3B8]">
                No shifts configured yet. Add your first shift schedule.
              </td>
            </tr>
            <tr
              v-for="(shift, i) in shifts"
              :key="shift.id"
              class="border-b border-[#F1F5F9] last:border-0"
              :class="i % 2 === 1 ? 'bg-[#FAFBFF]' : ''"
            >
              <td class="px-5 py-4">
                <p class="font-semibold text-[#0F172A]">{{ shift.name }}</p>
                <span v-if="shift.staff_type" class="mt-0.5 inline-block text-[10px] font-medium px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700">
                  {{ staffTypeLabel(shift.staff_type) }}
                </span>
                <span v-else class="text-[10px] text-[#CBD5E1]">General</span>
              </td>
              <td class="px-4 py-4 text-center font-mono text-[#0F172A]">
                {{ shift.morning_in }} – {{ shift.morning_out }}
              </td>
              <td class="px-4 py-4 text-center font-mono text-[#0F172A]">
                {{ shift.afternoon_in }} – {{ shift.afternoon_out }}
              </td>
              <td class="px-4 py-4 text-center">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-amber-50 text-amber-700">
                  <ClockIcon class="w-3 h-3" />
                  {{ shift.grace_period_minutes }}m
                </span>
              </td>
              <td class="px-4 py-4 text-center text-[#64748B] font-mono">
                {{ shift.required_hours }}h
              </td>
              <td class="px-4 py-4 text-center">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-blue-50 text-blue-700">
                  <UsersIcon class="w-3 h-3" />
                  {{ shift.employees_count ?? 0 }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="openEdit(shift)"
                    class="p-1.5 rounded-md text-[#94A3B8] hover:text-[#0E1EC0] hover:bg-[#EEF2FF] transition-colors"
                    title="Edit"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="confirmDelete(shift)"
                    :disabled="(shift.employees_count ?? 0) > 0"
                    class="p-1.5 rounded-md text-[#94A3B8] hover:text-red-600 hover:bg-red-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                    :title="(shift.employees_count ?? 0) > 0 ? 'Cannot delete: employees assigned' : 'Delete'"
                  >
                    <Trash2Icon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Info note -->
      <div class="mt-4 flex items-start gap-2 text-xs text-[#64748B] bg-white border border-[#E2E8F0] rounded-lg p-3">
        <InfoIcon class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-[#94A3B8]" />
        <span>Shifts define when employees are expected to arrive. The grace period allows a buffer before marking tardiness. Required hours are used for overtime and undertime computation.</span>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
          <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#0F172A]">{{ modal.editing ? 'Edit Shift' : 'Add Shift' }}</h2>
            <button @click="closeModal" class="text-[#94A3B8] hover:text-[#0F172A]">
              <XIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveShift" class="px-6 py-5 space-y-4">
            <div>
              <label class="modal-label">Shift Name</label>
              <input v-model="form.name" required placeholder="e.g. Regular Shift" class="modal-input" />
            </div>

            <div>
              <label class="modal-label">
                For Staff Type
                <span class="text-[#94A3B8] font-normal text-xs ml-1">(optional — blank = usable by all)</span>
              </label>
              <div class="grid grid-cols-4 gap-1.5 mt-1">
                <button
                  v-for="st in staffTypeOptions"
                  :key="st.value"
                  type="button"
                  @click="form.staff_type = form.staff_type === st.value ? '' : st.value"
                  class="py-1.5 px-2 rounded-md border text-xs font-medium transition-all text-center"
                  :class="form.staff_type === st.value
                    ? 'border-[#0E1EC0] bg-[#EEF2FF] text-[#0E1EC0]'
                    : 'border-[#E2E8F0] text-[#64748B] hover:border-[#C7D2F6] hover:bg-[#F8FAFF]'"
                >
                  {{ st.label }}
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="modal-label">Morning In</label>
                <input v-model="form.morning_in" type="time" required class="modal-input" />
              </div>
              <div>
                <label class="modal-label">Morning Out</label>
                <input v-model="form.morning_out" type="time" required class="modal-input" />
              </div>
              <div>
                <label class="modal-label">Afternoon In</label>
                <input v-model="form.afternoon_in" type="time" required class="modal-input" />
              </div>
              <div>
                <label class="modal-label">Afternoon Out</label>
                <input v-model="form.afternoon_out" type="time" required class="modal-input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="modal-label">Grace Period (minutes)</label>
                <input v-model.number="form.grace_period_minutes" type="number" min="0" max="60" required class="modal-input" />
              </div>
              <div>
                <label class="modal-label">Required Hours</label>
                <input v-model.number="form.required_hours" type="number" min="1" max="12" step="0.5" required class="modal-input" />
              </div>
            </div>

            <p v-if="modal.error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
              {{ modal.error }}
            </p>

            <div class="flex justify-end gap-3 pt-1">
              <button type="button" @click="closeModal" class="px-4 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
                Cancel
              </button>
              <button type="submit" :disabled="modal.saving" class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#0E1EC0] hover:bg-[#1228CC] disabled:opacity-60 transition-colors">
                {{ modal.saving ? 'Saving…' : (modal.editing ? 'Save Changes' : 'Add Shift') }}
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
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
          <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
            <Trash2Icon class="w-5 h-5 text-red-600" />
          </div>
          <h3 class="text-base font-semibold text-[#0F172A]">Delete Shift?</h3>
          <p class="text-sm text-[#64748B] mt-1">
            <span class="font-medium text-[#0F172A]">{{ delTarget?.name }}</span> will be permanently deleted.
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
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/PageHeader.vue';
import {
  Plus as PlusIcon, Pencil as PencilIcon, Trash2 as Trash2Icon,
  X as XIcon, Clock as ClockIcon, Users as UsersIcon, Info as InfoIcon,
  Timer as TimerIcon,
} from 'lucide-vue-next';

const { toast } = useToast();

const staffTypeOptions = [
  { value: 'faculty',        label: 'Faculty'        },
  { value: 'administrative', label: 'Administrative'  },
  { value: 'security',       label: 'Security Guard'  },
  { value: 'custodial',      label: 'Custodial'       },
  { value: 'maintenance',    label: 'Maintenance'     },
  { value: 'laboratory',     label: 'Lab Tech'        },
  { value: 'driver',         label: 'Driver'          },
  { value: 'other',          label: 'Other'           },
];

function staffTypeLabel(val) {
  return staffTypeOptions.find(s => s.value === val)?.label ?? val;
}

const shifts  = ref([]);
const loading = ref(false);
const delTarget = ref(null);
const deleting  = ref(false);
const modal = ref({ open: false, editing: null, saving: false, error: '' });
const form  = ref({ name: '', staff_type: '', morning_in: '08:00', morning_out: '12:00', afternoon_in: '13:00', afternoon_out: '17:00', grace_period_minutes: 15, required_hours: 8 });

async function load() {
  loading.value = true;
  try {
    const res = await axios.get('/api/shifts');
    // Enrich with employee count if not present
    shifts.value = res.data;
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  form.value  = { name: '', staff_type: '', morning_in: '08:00', morning_out: '12:00', afternoon_in: '13:00', afternoon_out: '17:00', grace_period_minutes: 15, required_hours: 8 };
  modal.value = { open: true, editing: null, saving: false, error: '' };
}

function openEdit(shift) {
  form.value  = {
    name: shift.name,
    staff_type: shift.staff_type ?? '',
    morning_in: shift.morning_in?.slice(0, 5) ?? '',
    morning_out: shift.morning_out?.slice(0, 5) ?? '',
    afternoon_in: shift.afternoon_in?.slice(0, 5) ?? '',
    afternoon_out: shift.afternoon_out?.slice(0, 5) ?? '',
    grace_period_minutes: shift.grace_period_minutes,
    required_hours: shift.required_hours,
  };
  modal.value = { open: true, editing: shift, saving: false, error: '' };
}

function closeModal() { modal.value.open = false; }

async function saveShift() {
  const f = form.value;
  if (f.morning_in >= f.morning_out) {
    modal.value.error = 'Morning time-out must be after morning time-in.';
    return;
  }
  if (f.morning_out >= f.afternoon_in) {
    modal.value.error = 'Afternoon time-in must be after morning time-out.';
    return;
  }
  if (f.afternoon_in >= f.afternoon_out) {
    modal.value.error = 'Afternoon time-out must be after afternoon time-in.';
    return;
  }
  modal.value.saving = true;
  modal.value.error  = '';
  try {
    if (modal.value.editing) {
      const res = await axios.put(`/api/shifts/${modal.value.editing.id}`, form.value);
      const idx = shifts.value.findIndex(s => s.id === modal.value.editing.id);
      if (idx !== -1) shifts.value[idx] = res.data;
      toast('Shift updated.', 'success');
    } else {
      const res = await axios.post('/api/shifts', form.value);
      shifts.value.push(res.data);
      toast('Shift created.', 'success');
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

function confirmDelete(shift) { delTarget.value = shift; }

async function doDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/api/shifts/${delTarget.value.id}`);
    shifts.value = shifts.value.filter(s => s.id !== delTarget.value.id);
    toast('Shift deleted.', 'warning');
    delTarget.value = null;
  } catch (e) {
    toast(e.response?.data?.message ?? 'Delete failed.', 'error');
    delTarget.value = null;
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
@reference "../css/app.css";

/* ── Table card ───────────────────────────────────── */
.shift-table-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden; box-shadow: 0 2px 12px rgba(8,14,128,0.06), 0 1px 3px rgba(8,14,128,0.04);
}

/* ── Modal form ───────────────────────────────────── */
.modal-label {
  display: block; font-size: 13px; font-weight: 600; color: #1E293B; margin-bottom: 5px;
}
.modal-input {
  width: 100%; padding: 8px 12px; font-size: 13px;
  border: 1px solid #E4E9F2; border-radius: 8px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.modal-input:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

@media (max-width: 640px) {
  .px-5.py-4 { padding: 12px 14px; }
}
</style>
