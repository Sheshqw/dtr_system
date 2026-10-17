<template>
  <div class="flex flex-col h-full" style="background: #F4F6FB;">

    <PageHeader title="Campuses" subtitle="Manage NEMSU campus locations" :icon="MapPinIcon">
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors ph-btn-primary">
        <PlusIcon class="w-4 h-4" />
        Add Campus
      </button>
    </PageHeader>

    <div class="flex-1 overflow-y-auto">
    <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

      <!-- Table card -->
      <div class="bg-white rounded-xl overflow-hidden" style="border: 1px solid #E5E7EB;">
        <div v-if="loading" class="p-8 text-center text-sm" style="color: #6B7A94;">Loading…</div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <caption class="sr-only">NEMSU campus list</caption>
            <thead>
              <tr style="background: #FAFAFA; border-bottom: 1px solid #F1F5F9;">
                <th scope="col" class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: #6B7A94;">Campus</th>
                <th scope="col" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: #6B7A94;">Code</th>
                <th scope="col" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: #6B7A94;">Address</th>
                <th scope="col" class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: #6B7A94;">Employees</th>
                <th scope="col" class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color: #6B7A94;">Status</th>
                <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="campus in campuses"
                :key="campus.id"
                class="transition-colors hover:bg-[#FAFAFA]"
                style="border-bottom: 1px solid #F9FAFB;"
              >
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                      :style="{ background: themeForCampusCode(campus.code).heroBand, boxShadow: `0 2px 6px ${themeForCampusCode(campus.code).heroBand}55` }">
                      {{ campus.code.slice(0, 2) }}
                    </div>
                    <span class="text-sm font-medium" style="color: #0F172A;">{{ campus.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <span
                    class="text-xs font-mono font-bold px-2 py-1 rounded"
                    :style="campusBadgeStyle(campus.code)"
                  >
                    {{ campus.code }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-sm" style="color: #64748B;">{{ campus.address ?? '—' }}</td>
                <td class="px-4 py-3.5 text-center text-sm font-semibold" style="color: #0F172A;">{{ campus.employees_count }}</td>
                <td class="px-4 py-3.5 text-center">
                  <span
                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                    :style="campus.is_active
                      ? 'background:#ECFDF5;color:#059669;'
                      : 'background:#FEF2F2;color:#DC2626;'"
                  >{{ campus.is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-5 py-3.5">
                  <div class="flex items-center justify-end gap-1">
                    <button
                      @click="openEdit(campus)"
                      class="p-1.5 rounded-lg transition-colors"
                      style="color: #6B7A94;"
                      @mouseover="e => e.currentTarget.style.color='#2E4A8C'"
                      @mouseout="e => e.currentTarget.style.color='#94A3B8'"
                      :aria-label="`Edit ${campus.name}`"
                    >
                      <PencilIcon class="w-4 h-4" />
                    </button>
                    <button
                      v-if="campus.employees_count === 0"
                      @click="confirmDelete(campus)"
                      class="p-1.5 rounded-lg transition-colors"
                      style="color: #6B7A94;"
                      @mouseover="e => e.currentTarget.style.color='#DC2626'"
                      @mouseout="e => e.currentTarget.style.color='#94A3B8'"
                      :aria-label="`Delete ${campus.name}`"
                    >
                      <Trash2Icon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="campuses.length === 0">
                <td colspan="6" class="px-5 py-16 text-center text-sm" style="color: #6B7A94;">No campuses found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /max-w container -->
    </div><!-- /scroll area -->
  </div><!-- /flex wrapper -->

  <!-- Create / Edit Modal -->
  <Teleport to="body">
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal" />
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="px-6 py-5 border-b border-[#E2E8F0] flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-[#EEF2FF] flex items-center justify-center flex-shrink-0">
            <MapPinIcon class="w-5 h-5 text-[#2E4A8C]" />
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-[#0F172A]">{{ modal.editing ? 'Edit Campus' : 'Add Campus' }}</h2>
            <p class="text-xs text-[#64748B]">{{ modal.editing ? 'Update campus details' : 'Register a new campus' }}</p>
          </div>
          <button @click="closeModal" class="text-[#6B7A94] hover:text-[#0F172A] transition-colors" aria-label="Close">
            <XIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Campus Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" required placeholder="e.g. Cantilan Campus"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] text-[#0F172A] bg-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Campus Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" required maxlength="20" placeholder="e.g. CAN"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] text-[#0F172A] bg-white uppercase" />
          </div>
          <div>
            <label class="block text-sm font-medium text-[#0F172A] mb-1.5">Address</label>
            <input v-model="form.address" type="text" placeholder="e.g. Cantilan, Surigao del Sur"
              class="w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] text-[#0F172A] bg-white" />
          </div>
          <div class="flex items-center gap-3">
            <input id="is_active" v-model="form.is_active" type="checkbox"
              class="w-4 h-4 rounded border-[#E2E8F0] text-[#2E4A8C] focus:ring-[#2E4A8C]" />
            <label for="is_active" class="text-sm text-[#0F172A]">Active</label>
          </div>
          <div v-if="formError" class="text-sm text-[#EF4444] bg-red-50 border border-red-100 rounded-lg px-3 py-2">
            {{ formError }}
          </div>
          <div class="flex gap-3 pt-1">
            <button type="button" @click="closeModal"
              class="flex-1 px-4 py-2 text-sm font-medium border border-[#E2E8F0] rounded-lg text-[#0F172A] hover:bg-[#F8FAFC] transition-colors">
              Cancel
            </button>
            <button type="submit" :disabled="saving"
              class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#2E4A8C] hover:bg-[#3D5CA3] text-white text-sm font-semibold rounded-lg disabled:opacity-60 transition-colors">
              <Loader2Icon v-if="saving" class="w-4 h-4 animate-spin" />
              {{ modal.editing ? 'Save Changes' : 'Add Campus' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import { Plus as PlusIcon, Pencil as PencilIcon, Trash2 as Trash2Icon, MapPin as MapPinIcon, X as XIcon, Loader2 as Loader2Icon } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { themeForCampusCode } from '@/composables/useCampusIdentity';
import { useThemeStore } from '@/stores/theme';
import { useReferenceDataStore } from '@/stores/referenceData';
import PageHeader from '@/components/PageHeader.vue';

const theme = useThemeStore();

// colorDim is a light pastel meant for a white card — in dark mode we
// wash the same hue in at low alpha instead, since the fixed pastel hex
// would look wrong against a dark row.
function campusBadgeStyle(code) {
  const t = themeForCampusCode(code);
  return theme.isDark
    ? { background: t.heroBand + '26', color: t.textColor }
    : { background: t.colorDim, color: t.heroBand };
}

const { toast }   = useToast();
const { confirm } = useConfirm();

const refData = useReferenceDataStore();
const { campuses } = storeToRefs(refData);
const loading   = ref(false);
const saving    = ref(false);
const formError = ref('');

const modal = reactive({ open: false, editing: null });
const form  = reactive({ name: '', code: '', address: '', is_active: true });

async function load() {
  loading.value = true;
  try {
    await refData.fetchCampuses(true); // fresh — this page is the source of truth
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  Object.assign(form, { name: '', code: '', address: '', is_active: true });
  formError.value = '';
  modal.editing = null;
  modal.open = true;
}

function openEdit(campus) {
  Object.assign(form, {
    name: campus.name,
    code: campus.code,
    address: campus.address ?? '',
    is_active: campus.is_active,
  });
  formError.value = '';
  modal.editing = campus;
  modal.open = true;
}

function closeModal() {
  modal.open = false;
}

async function submit() {
  formError.value = '';
  saving.value = true;
  try {
    const payload = { ...form };
    if (modal.editing) {
      await axios.put(`/api/campuses/${modal.editing.id}`, payload);
      toast('Campus updated.', 'success');
    } else {
      await axios.post('/api/campuses', payload);
      toast('Campus added.', 'success');
    }
    closeModal();
    await load();
  } catch (e) {
    formError.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? e.response?.data?.message
      ?? 'An error occurred.';
  } finally {
    saving.value = false;
  }
}

async function confirmDelete(campus) {
  const ok = await confirm({
    title:       `Delete "${campus.name}"?`,
    message:     'This cannot be undone.',
    confirmText: 'Delete',
  });
  if (!ok) return;
  try {
    await axios.delete(`/api/campuses/${campus.id}`);
    toast('Campus deleted.', 'success');
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Delete failed.', 'error');
  }
}

onMounted(load);
</script>
