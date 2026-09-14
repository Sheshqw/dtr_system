<template>
  <div class="flex flex-col h-full" style="background: #F4F6FB;">

    <PageHeader
      title="System Settings"
      subtitle="Customize your system's logo, name, and tagline"
      :icon="SettingsIcon"
    />

    <div class="flex-1 overflow-y-auto">
      <div class="max-w-screen-md mx-auto px-6 py-6 space-y-5">

        <!-- Loading -->
        <div v-if="loading" class="bg-white rounded-xl p-10 text-center" style="border:1px solid #E5E7EB;">
          <Loader2Icon class="w-6 h-6 mx-auto animate-spin" style="color:#94A3B8;" />
        </div>

        <template v-else>
          <!-- Logo card -->
          <div class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">
            <div class="px-5 py-4 border-b" style="border-color:#F1F5F9;">
              <h2 class="text-sm font-semibold" style="color:#0F172A;">Logo</h2>
              <p class="text-xs mt-0.5" style="color:#94A3B8;">Used across the sidebar, login page, and browser tab icon.</p>
            </div>
            <div class="p-5 flex items-center gap-5">
              <div class="relative flex-shrink-0">
                <img
                  :src="logoPreview || form.logo_url"
                  alt="Current logo"
                  class="w-20 h-20 rounded-full object-cover"
                  style="box-shadow: 0 0 0 3px #EEF1FF;"
                />
              </div>
              <div class="flex-1 min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <label class="settings-upload-btn">
                    <UploadIcon class="w-3.5 h-3.5" />
                    {{ logoFile ? 'Change Selected File' : 'Upload New Logo' }}
                    <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" class="sr-only" @change="onLogoChange" />
                  </label>
                  <button
                    v-if="form.hasCustomLogo && !logoFile"
                    @click="resetLogo"
                    :disabled="resettingLogo"
                    class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50"
                    style="background:#FEF2F2;color:#DC2626;"
                  >
                    {{ resettingLogo ? 'Resetting…' : 'Reset to Default' }}
                  </button>
                  <button
                    v-if="logoFile"
                    @click="clearLogoSelection"
                    class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
                    style="color:#64748B;"
                  >
                    Cancel selection
                  </button>
                </div>
                <p class="text-xs" style="color:#94A3B8;">JPG, PNG, or WEBP — up to 2MB. Square images look best.</p>
              </div>
            </div>
          </div>

          <!-- Branding text card -->
          <div class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">
            <div class="px-5 py-4 border-b" style="border-color:#F1F5F9;">
              <h2 class="text-sm font-semibold" style="color:#0F172A;">System Information</h2>
              <p class="text-xs mt-0.5" style="color:#94A3B8;">Shown throughout the app — sidebar, page headers, and browser tab.</p>
            </div>
            <div class="p-5 space-y-4">
              <div>
                <label class="settings-label">System Name</label>
                <input v-model="form.system_name" type="text" maxlength="60" class="settings-input" placeholder="e.g. NEMSU" />
                <p class="settings-hint">The main wordmark — appears in the sidebar and login page.</p>
              </div>
              <div>
                <label class="settings-label">Short Name</label>
                <input v-model="form.short_name" type="text" maxlength="30" class="settings-input" placeholder="e.g. UDTRMS" />
                <p class="settings-hint">A compact abbreviation — appears in the browser tab and page crumbs.</p>
              </div>
              <div>
                <label class="settings-label">Tagline</label>
                <input v-model="form.tagline" type="text" maxlength="150" class="settings-input" placeholder="e.g. University Daily Time Record Monitoring System" />
                <p class="settings-hint">A short description shown on the Dashboard header and browser tab.</p>
              </div>
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="text-sm px-4 py-3 rounded-xl" style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
            {{ error }}
          </div>

          <!-- Save bar -->
          <div class="flex items-center justify-between">
            <p v-if="form.updated_at" class="text-xs" style="color:#94A3B8;">
              Last updated {{ form.updated_at }}<span v-if="form.updated_by"> by {{ form.updated_by }}</span>
            </p>
            <p v-else></p>
            <button
              @click="save"
              :disabled="saving"
              class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg transition-colors disabled:opacity-50"
              style="background:#0E1EC0;color:#fff;"
            >
              <Loader2Icon v-if="saving" class="w-4 h-4 animate-spin" />
              <SaveIcon v-else class="w-4 h-4" />
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </template>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { useSettingsStore } from '@/stores/settings';
import PageHeader from '@/components/PageHeader.vue';
import {
  Settings as SettingsIcon,
  Upload   as UploadIcon,
  Save     as SaveIcon,
  Loader2  as Loader2Icon,
} from 'lucide-vue-next';

const { toast }    = useToast();
const { confirm }  = useConfirm();
const settingsStore = useSettingsStore();

const loading  = ref(true);
const saving   = ref(false);
const resettingLogo = ref(false);
const error    = ref('');

const logoFile    = ref(null);
const logoPreview = ref(null);

const form = reactive({
  system_name: '', short_name: '', tagline: '',
  logo_url: '', hasCustomLogo: false,
  updated_at: null, updated_by: null,
});

function applyResponse(data) {
  form.system_name   = data.system_name;
  form.short_name    = data.short_name;
  form.tagline       = data.tagline;
  form.logo_url      = data.logo_url || '/nemsu.png';
  form.hasCustomLogo  = !!data.logo_url;
  form.updated_at     = data.updated_at;
  form.updated_by     = data.updated_by;
}

async function load() {
  loading.value = true;
  try {
    const res = await axios.get('/api/system-settings');
    applyResponse(res.data);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to load system settings.', 'error');
  } finally {
    loading.value = false;
  }
}

function onLogoChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
  logoFile.value = file;
  logoPreview.value = URL.createObjectURL(file);
}

function clearLogoSelection() {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
  logoFile.value = null;
  logoPreview.value = null;
}

async function save() {
  error.value = '';
  saving.value = true;
  try {
    const fd = new FormData();
    fd.append('system_name', form.system_name);
    fd.append('short_name', form.short_name);
    fd.append('tagline', form.tagline);
    if (logoFile.value) fd.append('logo', logoFile.value);

    const res = await axios.post('/api/system-settings', fd);
    applyResponse(res.data);
    clearLogoSelection();
    await settingsStore.fetch(); // refresh the sidebar/header/login immediately
    toast('System settings saved.', 'success');
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? e.response?.data?.message
      ?? 'Failed to save settings.';
  } finally {
    saving.value = false;
  }
}

async function resetLogo() {
  const ok = await confirm({
    title:       'Reset to the default logo?',
    message:     'This removes your custom logo and reverts to the bundled default everywhere.',
    confirmText: 'Reset',
  });
  if (!ok) return;

  resettingLogo.value = true;
  try {
    const res = await axios.post('/api/system-settings/reset-logo');
    applyResponse(res.data);
    await settingsStore.fetch();
    toast('Logo reset to default.', 'success');
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to reset logo.', 'error');
  } finally {
    resettingLogo.value = false;
  }
}

onMounted(load);
onBeforeUnmount(() => {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
});
</script>

<style scoped>
.settings-label { display:block; font-size:12.5px; font-weight:600; color:#0F172A; margin-bottom:5px; }
.settings-input {
  width:100%; padding:8px 12px; font-size:13.5px; color:#0F172A;
  border:1px solid #E2E8F0; border-radius:9px; background:#fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.settings-input:focus { outline:none; border-color:#0E1EC0; box-shadow:0 0 0 3px rgba(14,30,192,0.08); }
.settings-hint { font-size:11.5px; color:#94A3B8; margin-top:4px; }

.settings-upload-btn {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; font-size:12.5px; font-weight:600;
  background:#EEF2FF; color:#1D4ED8; border-radius:9px;
  cursor:pointer; transition:background 0.15s;
}
.settings-upload-btn:hover { background:#DDE5FF; }
</style>
