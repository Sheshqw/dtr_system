<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Biometric Device" subtitle="Connect your time attendance terminal to UDTRMS" :icon="WifiIcon" />

    <div class="flex-1 overflow-y-auto p-6 space-y-6" style="background:#F4F6FB;">

    <!-- Method guide banner -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl" style="background:#EFF6FF;border:1px solid #BFDBFE;">
        <WifiIcon class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#2563EB;" />
        <div>
          <p class="text-sm font-semibold" style="color:#1E40AF;">Live Sync (TCP)</p>
          <p class="text-xs mt-0.5" style="color:#3B82F6;">
            Use when the biometric device and this server are on the <strong>same LAN network</strong>.
            Logs are pulled directly over TCP — no USB drive needed.
            Requires the IP in <code class="bg-blue-100 px-1 rounded">.env</code> to match the device.
          </p>
        </div>
      </div>
      <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl" style="background:#F0FDF4;border:1px solid #BBF7D0;">
        <UploadCloudIcon class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#15803D;" />
        <div>
          <p class="text-sm font-semibold" style="color:#14532D;">File Import (USB)</p>
          <p class="text-xs mt-0.5" style="color:#16A34A;">
            Use when the device is <strong>offline or on a different network</strong>.
            Export the attendance log from the device via USB flash drive (DAT, CSV, or TXT),
            then upload the file here. Works with any brand of biometric terminal.
          </p>
        </div>
      </div>
    </div>

    <!-- Top row: Device Status + How-To -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- Device Status Card -->
      <div class="bg-white rounded-2xl overflow-hidden bio-card">
        <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
               style="background: #EEF2FF;">
            <WifiIcon class="w-5 h-5 text-[#0E1EC0]" />
          </div>
          <div>
            <h2 class="font-semibold text-[#0F172A] text-sm">Device Status</h2>
            <p class="text-xs text-[#64748B]">Live connectivity check over LAN</p>
          </div>
        </div>

        <div class="p-5 space-y-4">
          <!-- IP / Port -->
          <div class="flex items-center justify-between text-sm">
            <span class="text-[#64748B]">Device IP</span>
            <code class="font-mono text-[#0F172A] bg-[#F1F5F9] px-2 py-0.5 rounded text-xs">{{ status.ip }}:{{ status.port }}</code>
          </div>

          <!-- Status indicator -->
          <div class="flex items-center justify-between">
            <span class="text-sm text-[#64748B]">Connection</span>
            <div v-if="statusLoading" class="flex items-center gap-1.5 text-xs text-[#94A3B8]">
              <Loader2Icon class="w-3.5 h-3.5 animate-spin" />
              Checking…
            </div>
            <div v-else class="flex items-center gap-1.5 text-xs font-semibold"
                 :class="status.reachable ? 'text-green-600' : 'text-red-500'">
              <span class="w-2 h-2 rounded-full"
                    :class="status.reachable ? 'bg-green-500' : 'bg-red-400'"></span>
              {{ status.reachable ? 'Online' : 'Offline / Unreachable' }}
            </div>
          </div>

          <!-- Last synced -->
          <div class="flex items-center justify-between text-sm">
            <span class="text-[#64748B]">Last Synced</span>
            <span class="text-[#0F172A] text-xs">{{ status.last_synced ?? 'Never' }}</span>
          </div>

          <!-- Buttons -->
          <div class="flex gap-2 pt-1">
            <button @click="checkStatus"
                    :disabled="statusLoading"
                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold border border-[#E2E8F0] text-[#475569] hover:bg-[#F8FAFC] transition-colors disabled:opacity-50">
              <RefreshCwIcon class="w-3.5 h-3.5" :class="statusLoading ? 'animate-spin' : ''" />
              Refresh
            </button>
            <button @click="syncDevice"
                    :disabled="syncing || !status.reachable"
                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-white transition-colors disabled:opacity-50"
                    style="background: linear-gradient(135deg, #0550A8, #0E1EC0);">
              <DownloadCloudIcon class="w-3.5 h-3.5" :class="syncing ? 'animate-pulse' : ''" />
              {{ syncing ? 'Syncing…' : 'Sync Now' }}
            </button>
          </div>

          <!-- Device offline notice -->
          <div v-if="!statusLoading && !status.reachable"
               class="rounded-lg px-3 py-2.5 text-xs text-amber-800 bg-amber-50 border border-amber-200 leading-relaxed">
            Device is unreachable. Make sure the device is powered on and connected to the same network.
            Use <strong>File Import</strong> below as an alternative.
          </div>
        </div>
      </div>

      <!-- Setup Guide Card -->
      <div class="bg-white rounded-2xl overflow-hidden bio-card">
        <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
               style="background: #FFF7ED;">
            <InfoIcon class="w-5 h-5 text-amber-600" />
          </div>
          <div>
            <h2 class="font-semibold text-[#0F172A] text-sm">Connection Guide</h2>
            <p class="text-xs text-[#64748B]">Steps to connect your BRANDING terminal</p>
          </div>
        </div>
        <div class="p-5 space-y-3 text-xs text-[#475569] leading-relaxed">
          <div class="flex gap-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EEF2FF] text-[#0E1EC0] font-bold flex items-center justify-center text-[10px]">1</span>
            <span>Plug the <strong>Ethernet cable</strong> from the device into the same router/switch as this computer.</span>
          </div>
          <div class="flex gap-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EEF2FF] text-[#0E1EC0] font-bold flex items-center justify-center text-[10px]">2</span>
            <span>On the device LCD: press <strong>Menu → Comm → IP Address</strong> and note the IP shown.</span>
          </div>
          <div class="flex gap-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EEF2FF] text-[#0E1EC0] font-bold flex items-center justify-center text-[10px]">3</span>
            <span>Update <code class="bg-[#F1F5F9] px-1 rounded">.env → BIOMETRIC_IP</code> to match, then restart the server.</span>
          </div>
          <div class="flex gap-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EEF2FF] text-[#0E1EC0] font-bold flex items-center justify-center text-[10px]">4</span>
            <span>Make sure each employee's <strong>Fingerprint ID</strong> in the device matches their ID in the Employees page.</span>
          </div>
          <div class="flex gap-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#EEF2FF] text-[#0E1EC0] font-bold flex items-center justify-center text-[10px]">5</span>
            <span>Click <strong>Sync Now</strong> to pull logs. For automatic sync, run <code class="bg-[#F1F5F9] px-1 rounded">php artisan schedule:work</code> in terminal.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- File Import Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-[#E2E8F0] overflow-hidden">
      <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
             style="background: #F0FDF4;">
          <UploadCloudIcon class="w-5 h-5 text-green-600" />
        </div>
        <div>
          <h2 class="font-semibold text-[#0F172A] text-sm">File Import (USB Export)</h2>
          <p class="text-xs text-[#64748B]">Upload the attendance file exported from your device via USB flash drive — works with any terminal brand</p>
        </div>
      </div>

      <div class="p-5 space-y-4">

        <!-- Drop zone -->
        <div
          class="relative border-2 border-dashed rounded-xl transition-colors cursor-pointer text-center py-10"
          :class="dragging ? 'border-[#0550A8] bg-[#EEF4FF]' : 'border-[#CBD5E1] hover:border-[#94A3B8]'"
          role="button"
          tabindex="0"
          title="Click or drag a file here to browse for an attendance export"
          @dragover.prevent="dragging = true"
          @dragleave="dragging = false"
          @drop.prevent="onDrop"
          @click="$refs.fileInput.click()"
          @keydown.enter="$refs.fileInput.click()"
          @keydown.space.prevent="$refs.fileInput.click()"
        >
          <UploadCloudIcon class="w-10 h-10 mx-auto mb-3 text-[#94A3B8]" />
          <p class="text-sm font-medium text-[#475569]">
            <span class="text-[#0550A8] font-semibold">Click to browse</span> or drag &amp; drop
          </p>
          <p class="text-xs text-[#94A3B8] mt-1">Supports <strong>.dat</strong>, <strong>.csv</strong>, <strong>.txt</strong> · Max 10 MB</p>

          <input ref="fileInput" type="file" accept=".dat,.csv,.txt" class="sr-only" @change="onFileSelect" />

          <!-- Selected file badge -->
          <div v-if="file" class="absolute inset-x-0 bottom-3 flex justify-center">
            <div class="flex items-center gap-2 bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 shadow-sm text-xs">
              <FileTextIcon class="w-3.5 h-3.5 text-[#0550A8]" />
              <span class="font-medium text-[#0F172A] max-w-[200px] truncate">{{ file.name }}</span>
              <button @click.stop="file = null" class="text-[#94A3B8] hover:text-red-400 transition-colors">
                <XIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>

        <!-- Format hint -->
        <div class="rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] px-4 py-3 text-xs text-[#64748B] space-y-1">
          <p class="font-semibold text-[#475569] mb-1">Expected file format (tab, comma, or space separated):</p>
          <code class="block text-[#475569]">UserID &nbsp; DateTime &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Status</code>
          <code class="block text-[#475569]">1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2024-05-24 08:00:00 &nbsp; 0</code>
          <code class="block text-[#475569]">1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 2024-05-24 17:00:00 &nbsp; 1</code>
          <p class="mt-1">Status: <strong>0</strong> = Check-In &nbsp;·&nbsp; <strong>1</strong> = Check-Out &nbsp;·&nbsp; empty = auto-detected</p>
        </div>

        <!-- Import button -->
        <button
          @click="importFile"
          :disabled="!file || importing"
          class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          style="background: linear-gradient(135deg, #15803d, #166534);"
        >
          <UploadCloudIcon v-if="!importing" class="w-4 h-4" />
          <Loader2Icon v-else class="w-4 h-4 animate-spin" />
          {{ importing ? 'Importing…' : 'Import File' }}
        </button>
      </div>
    </div>

    <!-- Results -->
    <div v-if="result" class="bg-white rounded-2xl shadow-sm border border-[#E2E8F0] overflow-hidden">
      <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
        <h2 class="font-semibold text-[#0F172A] text-sm">Import / Sync Results</h2>
        <button @click="result = null" class="text-[#94A3B8] hover:text-[#475569] transition-colors">
          <XIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Summary chips -->
      <div class="px-5 pt-4 pb-2 flex flex-wrap gap-3">
        <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-xs">
          <CheckCircleIcon class="w-4 h-4 text-green-600" />
          <span class="font-bold text-green-700">{{ result.imported }}</span>
          <span class="text-green-600">Imported</span>
        </div>
        <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs">
          <MinusCircleIcon class="w-4 h-4 text-amber-600" />
          <span class="font-bold text-amber-700">{{ result.skipped }}</span>
          <span class="text-amber-600">Skipped (duplicate)</span>
        </div>
        <div class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs">
          <AlertCircleIcon class="w-4 h-4 text-red-500" />
          <span class="font-bold text-red-600">{{ result.unknown }}</span>
          <span class="text-red-500">Unknown ID</span>
        </div>
      </div>

      <!-- Detail table -->
      <div v-if="result.details?.length" class="px-5 pb-5">
        <div class="overflow-x-auto rounded-xl border border-[#E2E8F0] mt-3">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-[#F8FAFC] text-[#64748B]">
                <th class="text-left px-3 py-2.5 font-semibold">Fingerprint ID</th>
                <th class="text-left px-3 py-2.5 font-semibold">Employee</th>
                <th class="text-left px-3 py-2.5 font-semibold">Date / Time</th>
                <th class="text-left px-3 py-2.5 font-semibold">Punch</th>
                <th class="text-left px-3 py-2.5 font-semibold">Result</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#F1F5F9]">
              <tr v-for="(d, i) in result.details" :key="i"
                  class="hover:bg-[#F8FAFC] transition-colors">
                <td class="px-3 py-2 font-mono text-[#475569]">{{ d.fingerprint_id }}</td>
                <td class="px-3 py-2 text-[#0F172A]">{{ d.name ?? '—' }}</td>
                <td class="px-3 py-2 text-[#475569]">{{ d.time ?? '—' }}</td>
                <td class="px-3 py-2">
                  <span v-if="d.punch_type"
                        class="px-2 py-0.5 rounded-full font-semibold text-[10px]"
                        :class="d.punch_type === 'IN' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'">
                    {{ d.punch_type }}
                  </span>
                  <span v-else class="text-[#94A3B8]">—</span>
                </td>
                <td class="px-3 py-2">
                  <span class="px-2 py-0.5 rounded-full font-semibold text-[10px]"
                        :class="{
                          'bg-green-50 text-green-700':  d.result === 'imported',
                          'bg-amber-50 text-amber-700':  d.result === 'duplicate',
                          'bg-red-50   text-red-600':    d.result === 'unknown',
                        }">
                    {{ d.result }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /scroll area -->
  </div><!-- /flex wrapper -->
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import { useToast } from '@/composables/useToast';
import {
  WifiIcon, RefreshCwIcon, DownloadCloudIcon, UploadCloudIcon,
  FileTextIcon, XIcon, InfoIcon, Loader2Icon,
  CheckCircleIcon, MinusCircleIcon, AlertCircleIcon,
} from 'lucide-vue-next';

const { toast } = useToast();

// ── state ──────────────────────────────────────────────────────────────────
const status       = ref({ ip: '…', port: 4370, reachable: false, last_synced: null });
const statusLoading = ref(false);
const syncing      = ref(false);
const importing    = ref(false);
const dragging     = ref(false);
const file         = ref(null);
const result       = ref(null);
const fileInput    = ref(null);

// ── lifecycle ───────────────────────────────────────────────────────────────
onMounted(checkStatus);

// ── methods ─────────────────────────────────────────────────────────────────
async function checkStatus() {
  statusLoading.value = true;
  try {
    const { data } = await axios.get('/api/biometric/status');
    status.value = data;
  } catch (e) {
    toast(e.response?.data?.message ?? 'Could not reach the server to check device status.', 'error');
  } finally {
    statusLoading.value = false;
  }
}

async function syncDevice() {
  syncing.value = true;
  result.value  = null;
  try {
    const { data } = await axios.post('/api/biometric/sync');
    result.value = data;
    toast(`Sync complete — ${data.imported} record${data.imported !== 1 ? 's' : ''} imported.`, 'success');
    await checkStatus();
  } catch (err) {
    toast(err.response?.data?.error ?? 'Sync failed. Check the device connection.', 'error');
  } finally {
    syncing.value = false;
  }
}

function onDrop(e) {
  dragging.value = false;
  const dropped = e.dataTransfer.files[0];
  if (dropped) file.value = dropped;
}

function onFileSelect(e) {
  file.value = e.target.files[0] ?? null;
  if (fileInput.value) fileInput.value.value = '';
}

async function importFile() {
  if (!file.value) return;
  importing.value = true;
  result.value    = null;

  const form = new FormData();
  form.append('file', file.value);

  try {
    const { data } = await axios.post('/api/biometric/import', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    result.value = data;
    file.value   = null;
    toast(`Import complete — ${data.imported} record${data.imported !== 1 ? 's' : ''} imported.`, 'success');
  } catch (err) {
    toast(err.response?.data?.error ?? 'Import failed. Please check the file format.', 'error');
  } finally {
    importing.value = false;
  }
}
</script>

<style scoped>

/* ── Info cards ──────────────────────────────────── */
.bio-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 16px;
  overflow: hidden; box-shadow: 0 2px 12px rgba(8,14,128,0.06), 0 1px 3px rgba(8,14,128,0.04);
}
.bio-card-head {
  padding: 14px 20px; border-bottom: 1px solid #EEF2FA; background: #F7F8FD;
  display: flex; align-items: center; gap: 12px;
}
.bio-icon {
  width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}

/* ── Status pill ─────────────────────────────────── */
.status-online  { color: #059669; font-weight: 600; display: flex; align-items: center; gap: 6px; font-size: 12px; }
.status-offline { color: #DC2626; font-weight: 600; display: flex; align-items: center; gap: 6px; font-size: 12px; }

/* ── Sync button ─────────────────────────────────── */
.sync-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 7px;
  padding: 8px 14px; border-radius: 9px; font-size: 12px; font-weight: 600;
  color: #fff; border: none; cursor: pointer;
  background: linear-gradient(135deg, #1228CC 0%, #0E1EC0 100%);
  transition: opacity 0.15s;
}
.sync-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Import upload zone ──────────────────────────── */
.upload-zone {
  border: 2px dashed #C9D4E8; border-radius: 12px; padding: 28px 20px;
  text-align: center; cursor: pointer; background: #F7F8FD;
  transition: border-color 0.15s, background 0.15s;
}
.upload-zone:hover { border-color: #0E1EC0; background: #EEF2FF; }

/* ── Mobile ──────────────────────────────────────── */
@media (max-width: 768px) {
  .grid.grid-cols-1.lg\\:grid-cols-2 { grid-template-columns: 1fr; }
}
</style>
