<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Departments" subtitle="Manage organizational units and assign department heads" :icon="Building2Icon">
      <button @click="openAdd" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors ph-btn-primary">
        <PlusIcon class="w-4 h-4" />
        Add Department
      </button>
    </PageHeader>

    <!-- Campus filter (super_admin only) -->
    <div v-if="auth.isSuperAdmin && campuses.length" class="px-6 py-3 border-b border-[#E2E8F0] bg-white flex items-center gap-2 flex-wrap">
      <button @click="campusFilter = ''"
        :class="['px-3 py-1 rounded-full text-xs font-semibold transition-colors', !campusFilter ? 'bg-[#2E4A8C] text-white' : 'bg-[#F1F5F9] text-[#64748B] hover:bg-[#E2E8F0]']">
        All Campuses
      </button>
      <button v-for="c in campuses" :key="c.id" @click="campusFilter = c.id"
        :class="['px-3 py-1 rounded-full text-xs font-semibold transition-colors', campusFilter == c.id ? 'bg-[#2E4A8C] text-white' : 'bg-[#F1F5F9] text-[#64748B] hover:bg-[#E2E8F0]']">
        {{ c.name }}
      </button>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto bg-[#F4F6FB] p-6">

      <!-- Loading skeleton -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-[#E2E8F0] p-5 animate-pulse">
          <div class="flex gap-3">
            <div class="w-10 h-10 rounded-lg bg-slate-200 flex-shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-4 bg-slate-200 rounded w-3/4"></div>
              <div class="h-3 bg-slate-100 rounded w-1/2"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="departments.length === 0" class="text-center py-16">
        <Building2Icon class="w-12 h-12 mx-auto text-[#94A3B8] mb-3" />
        <p class="text-[#64748B] font-medium">No departments yet</p>
        <p class="text-sm text-[#6B7A94] mt-1">Create departments to organize your employees.</p>
        <button @click="openAdd" class="mt-4 px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#2E4A8C] hover:bg-[#3D5CA3] transition-colors">
          Add First Department
        </button>
      </div>

      <!-- Cards grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="dept in visibleDepts"
          :key="dept.id"
          class="dept-card"
          :style="{ '--dept-accent': avatarStyle(dept.name).background }"
        >
          <div class="flex items-start gap-3">
            <!-- Icon — colored per department, so a long roster of cards
                 reads as distinct entries at a glance instead of a wall
                 of identical navy squares. -->
            <div class="dept-icon" :style="avatarStyle(dept.name)">
              <Building2Icon class="w-5 h-5 text-white" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-semibold text-[#0F172A] truncate">{{ dept.name }}</h3>
                <span class="text-xs font-mono font-semibold px-1.5 py-0.5 rounded bg-[#F0F4FF] text-[#2E4A8C]">{{ dept.code }}</span>
              </div>
              <p v-if="auth.isSuperAdmin && dept.campus_name" class="text-[10px] text-[#6B7A94] mt-0.5">
                📍 {{ dept.campus_name }}
              </p>
              <p v-if="dept.head" class="text-xs text-[#64748B] mt-1 truncate">
                Head: <span class="font-medium text-[#0F172A]">{{ dept.head.name }}</span>
              </p>
              <p v-else class="text-xs text-[#6B7A94] mt-1">No head assigned</p>
            </div>
          </div>

          <!-- Stats + Actions -->
          <div class="mt-4 flex items-center justify-between">
            <div class="flex items-center gap-1.5 text-sm text-[#64748B]">
              <UsersIcon class="w-4 h-4" />
              <span><span class="font-semibold text-[#0F172A]">{{ dept.employees_count }}</span> employee{{ dept.employees_count !== 1 ? 's' : '' }}</span>
            </div>
            <div class="flex items-center gap-1">
              <button
                @click="openEdit(dept)"
                class="p-1.5 rounded-md text-[#6B7A94] hover:text-[#2E4A8C] hover:bg-[#EEF2FF] transition-colors"
                title="Edit"
              >
                <PencilIcon class="w-4 h-4" />
              </button>
              <button
                @click="confirmDelete(dept)"
                :disabled="dept.employees_count > 0"
                class="p-1.5 rounded-md text-[#6B7A94] hover:text-red-600 hover:bg-red-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :title="dept.employees_count > 0 ? 'Cannot delete: has employees' : 'Delete'"
              >
                <Trash2Icon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center justify-between">
            <h2 class="text-base font-semibold text-[#0F172A]">{{ modal.editing ? 'Edit Department' : 'Add Department' }}</h2>
            <button @click="closeModal" class="text-[#6B7A94] hover:text-[#0F172A]"><XIcon class="w-5 h-5" /></button>
          </div>

          <form @submit.prevent="saveDept" class="px-6 py-5 space-y-4">
            <!-- Campus selector — super_admin only, add mode only -->
            <div v-if="auth.isSuperAdmin && !modal.editing">
              <label class="modal-label">Campus <span class="text-red-400">*</span></label>
              <select v-model="form.campus_id" required class="modal-input">
                <option value="">— Select Campus —</option>
                <option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="modal-label">Department Name</label>
              <input v-model="form.name" required placeholder="e.g. College of Engineering" class="modal-input" />
            </div>
            <div>
              <label class="modal-label">Short Code</label>
              <input v-model="form.code" required placeholder="e.g. COE" class="modal-input" style="text-transform:uppercase;" />
            </div>
            <div>
              <label class="modal-label">Department Head <span class="text-[#6B7A94] font-normal">(optional)</span></label>
              <!-- Search -->
              <div class="relative">
                <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#6B7A94]" />
                <input
                  v-model="empSearch"
                  @focus="showEmpDrop = true"
                  @blur="hideEmpDrop"
                  class="modal-input !pl-9"
                  placeholder="Search employee name…"
                  autocomplete="off"
                />
                <div
                  v-if="showEmpDrop && filteredEmps.length"
                  class="absolute z-20 top-full left-0 right-0 mt-1 bg-white border border-[#E2E8F0] rounded-lg shadow-lg max-h-44 overflow-y-auto"
                >
                  <button
                    v-for="emp in filteredEmps"
                    :key="emp.id"
                    type="button"
                    @mousedown.prevent="selectEmp(emp)"
                    class="w-full text-left px-4 py-2.5 hover:bg-[#F8FAFC] text-sm text-[#0F172A]"
                  >
                    {{ emp.name }}
                    <span class="text-xs text-[#6B7A94] ml-1">{{ emp.employee_id }}</span>
                  </button>
                </div>
              </div>
              <!-- Selected -->
              <div v-if="selectedEmp" class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg bg-[#EEF2FF] border border-[#C7D2F6]">
                <span class="flex-1 text-sm text-[#0F172A] font-medium">{{ selectedEmp.name }}</span>
                <button type="button" @click="clearEmp" class="text-[#6B7A94] hover:text-[#EF4444]">
                  <XIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <p v-if="modal.error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
              {{ modal.error }}
            </p>

            <div class="flex justify-end gap-3 pt-1">
              <button type="button" @click="closeModal" class="px-4 py-2 text-sm border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">Cancel</button>
              <button type="submit" :disabled="modal.saving" class="px-4 py-2 text-sm font-medium text-white rounded-lg bg-[#2E4A8C] hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors">
                {{ modal.saving ? 'Saving…' : (modal.editing ? 'Save Changes' : 'Add Department') }}
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
        <div class="modal-panel relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
          <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
            <Trash2Icon class="w-5 h-5 text-red-600" />
          </div>
          <h3 class="text-base font-semibold text-[#0F172A]">Delete Department?</h3>
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
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import { useToast } from '@/composables/useToast';
import { useAuthStore } from '@/stores/auth';
import { useReferenceDataStore } from '@/stores/referenceData';
import { useAvatarColor } from '@/composables/useAvatarColor';
import PageHeader from '@/components/PageHeader.vue';
import {
  Plus as PlusIcon, Pencil as PencilIcon, Trash2 as Trash2Icon,
  X as XIcon, Search as SearchIcon,
  Building2 as Building2Icon, Users as UsersIcon,
} from 'lucide-vue-next';

const { toast } = useToast();
const auth    = useAuthStore();
const refData = useReferenceDataStore();
const { avatarStyle } = useAvatarColor();

const { departments, employees, campuses } = storeToRefs(refData);
const loading      = ref(false);
const delTarget    = ref(null);
const deleting     = ref(false);
const campusFilter = ref('');
const modal        = ref({ open: false, editing: null, saving: false, error: '' });
const form         = ref({ name: '', code: '', head_id: null, campus_id: '' });
const empSearch    = ref('');
const showEmpDrop  = ref(false);
const selectedEmp  = ref(null);

const filteredEmps = computed(() => {
  const s = empSearch.value.toLowerCase();
  return employees.value.filter(e => e.name.toLowerCase().includes(s) || e.employee_id.toLowerCase().includes(s)).slice(0, 8);
});

const visibleDepts = computed(() => {
  if (!auth.isSuperAdmin || !campusFilter.value) return departments.value;
  return departments.value.filter(d => d.campus_id == campusFilter.value);
});

async function load() {
  loading.value = true;
  try {
    // Departments is always a fresh pull — this page is its source of
    // truth. Employees/campuses are just reference data for the dropdowns.
    await Promise.all([
      refData.fetchDepartments(true),
      refData.fetchEmployees(),
      auth.isSuperAdmin ? refData.fetchCampuses() : Promise.resolve(),
    ]);
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  form.value      = { name: '', code: '', head_id: null, campus_id: auth.isSuperAdmin ? '' : auth.employee?.campus_id };
  selectedEmp.value = null;
  empSearch.value   = '';
  modal.value = { open: true, editing: null, saving: false, error: '' };
}

function openEdit(dept) {
  form.value      = { name: dept.name, code: dept.code, head_id: dept.head?.id ?? null };
  selectedEmp.value = dept.head ? employees.value.find(e => e.id === dept.head.id) ?? dept.head : null;
  empSearch.value   = '';
  modal.value = { open: true, editing: dept, saving: false, error: '' };
}

function closeModal() { modal.value.open = false; }

function selectEmp(emp) {
  selectedEmp.value = emp;
  form.value.head_id = emp.id;
  empSearch.value = '';
  showEmpDrop.value = false;
}

function clearEmp() {
  selectedEmp.value = null;
  form.value.head_id = null;
}

function hideEmpDrop() { setTimeout(() => { showEmpDrop.value = false; }, 150); }

async function saveDept() {
  modal.value.saving = true;
  modal.value.error  = '';
  form.value.code = form.value.code.toUpperCase();
  try {
    if (modal.value.editing) {
      const res = await axios.put(`/api/departments/${modal.value.editing.id}`, form.value);
      const idx = departments.value.findIndex(d => d.id === modal.value.editing.id);
      if (idx !== -1) departments.value[idx] = res.data;
      toast('Department updated.', 'success');
    } else {
      const res = await axios.post('/api/departments', form.value);
      departments.value.push(res.data);
      departments.value.sort((a, b) => a.name.localeCompare(b.name));
      toast('Department created.', 'success');
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

function confirmDelete(dept) { delTarget.value = dept; }

async function doDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/api/departments/${delTarget.value.id}`);
    departments.value = departments.value.filter(d => d.id !== delTarget.value.id);
    toast('Department deleted.', 'warning');
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

/* ── Department cards ─────────────────────────────── */
.dept-card {
  position: relative;
  overflow: hidden;
  background: #fff; border: 1px solid #E4E9F2; border-radius: 14px;
  padding: 20px; box-shadow: 0 2px 8px rgba(27, 42, 94,0.05), 0 1px 2px rgba(27, 42, 94,0.03);
  transition: box-shadow 0.2s, transform 0.2s;
}
.dept-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: var(--dept-accent, linear-gradient(135deg, #3D5CA3, #2E4A8C));
}
.dept-card:hover { box-shadow: 0 6px 24px rgba(27, 42, 94,0.10); transform: translateY(-2px); }

.dept-icon {
  width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 3px 10px rgba(27, 42, 94,0.18);
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
.modal-input:focus { outline: none; border-color: #2E4A8C; box-shadow: 0 0 0 3px rgba(46, 74, 140,0.08); }

/* ── Mobile ───────────────────────────────────────── */
@media (max-width: 640px) {
  .grid.grid-cols-1 { gap: 12px; }
  .dept-card { padding: 14px; }
}
</style>
