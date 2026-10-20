<template>
  <div class="flex flex-col h-full" style="background: #F4F6FB;">

    <PageHeader
      title="Database Backup"
      subtitle="Create, download, and restore system backups to keep your data safe"
      :icon="DatabaseIcon"
    >
      <button
        @click="createBackup"
        :disabled="creating"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors disabled:opacity-50 ph-btn-primary"
      >
        <Loader2Icon v-if="creating" class="w-4 h-4 animate-spin" />
        <DatabaseIcon v-else class="w-4 h-4" />
        {{ creating ? 'Creating…' : 'Create Backup' }}
      </button>
    </PageHeader>

    <div class="flex-1 overflow-y-auto">
      <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="stat-tile" style="--stat-color:#4338CA;--stat-glow:rgba(67,56,202,0.28);">
            <div class="stat-tile-icon">
              <ArchiveIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="stat-tile-label" style="margin-top:0;">Total Backups</p>
              <p class="stat-tile-value" style="font-size:20px;">{{ summary.total_files ?? 0 }}</p>
            </div>
          </div>

          <div class="stat-tile" style="--stat-color:#15803D;--stat-glow:rgba(21,128,61,0.28);">
            <div class="stat-tile-icon">
              <HardDriveIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="stat-tile-label" style="margin-top:0;">Storage Used</p>
              <p class="stat-tile-value" style="font-size:20px;">{{ summary.total_size ?? '0 B' }}</p>
            </div>
          </div>

          <div class="stat-tile" style="--stat-color:#C2410C;--stat-glow:rgba(194,65,12,0.28);">
            <div class="stat-tile-icon">
              <ClockIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="stat-tile-label" style="margin-top:0;">Last Backup</p>
              <p class="text-sm font-semibold truncate" style="color:#0F172A;">
                {{ summary.last_backup ? formatRelative(summary.last_backup) : 'Never' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Info banner -->
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl" style="background:#EFF6FF;border:1px solid #BFDBFE;">
          <InfoIcon class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#3B82F6;" />
          <div class="text-sm" style="color:#1D4ED8;">
            <strong>HR Officer</strong> backups include your campus data only. &nbsp;
            <strong>Super Admin</strong> backups include all campuses. &nbsp;
            Backups are saved to <code class="font-mono px-1 rounded" style="background:#DBEAFE;">storage/app/backups/</code> on the server.
            <span class="font-semibold">Download a copy regularly</span> and store it in a safe location.
          </div>
        </div>

        <!-- Backup list -->
        <div class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">

          <div class="px-5 py-3.5 flex items-center justify-between" style="border-bottom:1px solid #F1F5F9;">
            <h2 class="text-sm font-semibold" style="color:#0F172A;">Available Backups</h2>
            <button
              @click="load"
              :disabled="loading"
              class="text-xs font-medium transition-colors flex items-center gap-1"
              style="color:#64748B;"
            >
              <RefreshCwIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
              Refresh
            </button>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="p-10 text-center">
            <Loader2Icon class="w-7 h-7 mx-auto animate-spin mb-3" style="color:#C4B5FD;" />
            <p class="text-sm" style="color:#6B7A94;">Loading backups…</p>
          </div>

          <!-- Empty -->
          <div v-else-if="backups.length === 0" class="p-14 text-center">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#F8FAFC;">
              <DatabaseIcon class="w-7 h-7 opacity-25" style="color:#64748B;" />
            </div>
            <p class="text-sm font-semibold mb-1" style="color:#374151;">No backups yet</p>
            <p class="text-xs" style="color:#6B7A94;">Click "Create Backup" above to generate your first backup.</p>
          </div>

          <!-- Table -->
          <div v-else>
            <table class="w-full">
              <thead>
                <tr style="background:#FAFAFA;border-bottom:1px solid #F1F5F9;">
                  <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7A94;">Backup File</th>
                  <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7A94;">Created By</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7A94;">Records</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7A94;">Size</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#6B7A94;">Date</th>
                  <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="backup in backups" :key="backup.filename">
                  <!-- Main row -->
                  <tr
                    class="transition-colors hover:bg-[#FAFAFA] cursor-pointer"
                    style="border-bottom:1px solid #F9FAFB;"
                    role="button"
                    tabindex="0"
                    :aria-expanded="expanded === backup.filename"
                    :title="`${expanded === backup.filename ? 'Collapse' : 'Expand'} details for ${backup.filename}`"
                    @click="toggleExpand(backup.filename)"
                    @keydown.enter="toggleExpand(backup.filename)"
                    @keydown.space.prevent="toggleExpand(backup.filename)"
                  >
                    <td class="px-5 py-3.5">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#EEF2FF;">
                          <FileIcon class="w-4 h-4" style="color:#4338CA;" />
                        </div>
                        <div>
                          <p class="text-xs font-mono font-medium" style="color:#0F172A;">{{ backup.filename }}</p>
                          <span class="inline-flex items-center mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold" style="background:#F0F9FF;color:#0284C7;">
                            {{ backup.campus }}
                          </span>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3.5">
                      <p class="text-xs" style="color:#374151;">{{ backup.created_by }}</p>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                      <span class="text-sm font-semibold" style="color:#0F172A;">
                        {{ backup.total_records !== null ? backup.total_records.toLocaleString() : '—' }}
                      </span>
                    </td>
                    <td class="px-4 py-3.5 text-center text-sm" style="color:#64748B;">{{ backup.size }}</td>
                    <td class="px-4 py-3.5 text-center">
                      <p class="text-xs font-mono" style="color:#64748B;">{{ backup.created_at }}</p>
                    </td>
                    <td class="px-5 py-3.5">
                      <div class="flex items-center justify-end gap-2">
                        <ChevronDownIcon
                          class="w-4 h-4 transition-transform duration-200 flex-shrink-0"
                          :class="expanded === backup.filename ? 'rotate-180' : ''"
                          style="color:#6B7A94;"
                        />
                        <button
                          @click.stop="download(backup.filename)"
                          class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-lg transition-colors"
                          style="background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;"
                          @mouseover="e => e.currentTarget.style.background='#DCFCE7'"
                          @mouseout="e => e.currentTarget.style.background='#F0FDF4'"
                        >
                          <DownloadIcon class="w-3.5 h-3.5" />
                          Download
                        </button>
                        <button
                          v-if="isSuperAdmin"
                          @click.stop="confirmRestore(backup)"
                          class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-lg transition-colors"
                          style="background:#FFFBEB;color:#92400E;border:1px solid #FDE68A;"
                          @mouseover="e => e.currentTarget.style.background='#FEF3C7'"
                          @mouseout="e => e.currentTarget.style.background='#FFFBEB'"
                        >
                          <RotateCcwIcon class="w-3.5 h-3.5" />
                          Restore
                        </button>
                        <button
                          v-if="isSuperAdmin"
                          @click.stop="confirmDelete(backup.filename)"
                          class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-lg transition-colors"
                          style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;"
                          @mouseover="e => e.currentTarget.style.background='#FEE2E2'"
                          @mouseout="e => e.currentTarget.style.background='#FEF2F2'"
                        >
                          <Trash2Icon class="w-3.5 h-3.5" />
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Expanded detail row -->
                  <tr v-if="expanded === backup.filename" style="background:#F8FAFC;border-bottom:1px solid #F1F5F9;">
                    <td colspan="6" class="px-6 py-4">
                      <div v-if="Object.keys(backup.table_counts).length" class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider mb-3" style="color:#6B7A94;">Records per table</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                          <div
                            v-for="(count, table) in backup.table_counts"
                            :key="table"
                            class="flex items-center justify-between px-3 py-2 rounded-lg"
                            style="background:white;border:1px solid #E5E7EB;"
                          >
                            <span class="text-xs font-mono" style="color:#374151;">{{ table }}</span>
                            <span
                              class="text-xs font-bold ml-2 px-1.5 py-0.5 rounded"
                              :style="count > 0
                                ? 'background:#EEF2FF;color:#4338CA;'
                                : 'background:#F9FAFB;color:#6B7A94;'"
                            >{{ count.toLocaleString() }}</span>
                          </div>
                        </div>
                      </div>
                      <div v-else class="text-xs" style="color:#6B7A94;">
                        No table detail available — this backup was created before metadata tracking.
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <!-- Restore confirmation modal -->
    <div
      v-if="restoreTarget"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background:rgba(0,0,0,0.5);"
      @click.self="restoreTarget = null"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#FEF3C7;">
            <AlertTriangleIcon class="w-5 h-5" style="color:#D97706;" />
          </div>
          <div>
            <h3 class="text-base font-bold" style="color:#0F172A;">Restore Database Backup</h3>
            <p class="text-sm mt-1" style="color:#64748B;">
              This will overwrite existing data with the contents of this backup file. This action <strong>cannot be undone</strong>.
            </p>
          </div>
        </div>

        <div class="rounded-xl px-4 py-3 space-y-1" style="background:#F8FAFC;border:1px solid #E5E7EB;">
          <div class="flex justify-between text-xs">
            <span style="color:#6B7A94;">File</span>
            <span class="font-mono font-medium" style="color:#0F172A;">{{ restoreTarget?.filename }}</span>
          </div>
          <div class="flex justify-between text-xs">
            <span style="color:#6B7A94;">Campus</span>
            <span class="font-medium" style="color:#0F172A;">{{ restoreTarget?.campus }}</span>
          </div>
          <div class="flex justify-between text-xs">
            <span style="color:#6B7A94;">Total Records</span>
            <span class="font-medium" style="color:#0F172A;">{{ restoreTarget?.total_records?.toLocaleString() ?? '—' }}</span>
          </div>
          <div class="flex justify-between text-xs">
            <span style="color:#6B7A94;">Created</span>
            <span class="font-mono" style="color:#0F172A;">{{ restoreTarget?.created_at }}</span>
          </div>
        </div>

        <div class="flex gap-3 pt-1">
          <button
            @click="restoreTarget = null"
            class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors"
            style="background:#F1F5F9;color:#374151;"
          >
            Cancel
          </button>
          <button
            @click="doRestore"
            :disabled="restoring"
            class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50"
            style="background:#D97706;color:white;"
          >
            <span v-if="restoring" class="flex items-center justify-center gap-2">
              <Loader2Icon class="w-4 h-4 animate-spin" /> Restoring…
            </span>
            <span v-else>Yes, Restore</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { extractErrorMessage } from '@/composables/useApiError';
import PageHeader from '@/components/PageHeader.vue';
import {
  Database as DatabaseIcon,
  File as FileIcon,
  Download as DownloadIcon,
  Trash2 as Trash2Icon,
  RefreshCw as RefreshCwIcon,
  Loader2 as Loader2Icon,
  Info as InfoIcon,
  AlertTriangle as AlertTriangleIcon,
  Archive as ArchiveIcon,
  HardDrive as HardDriveIcon,
  Clock as ClockIcon,
  ChevronDown as ChevronDownIcon,
  RotateCcw as RotateCcwIcon,
} from 'lucide-vue-next';

const auth        = useAuthStore();
const isSuperAdmin = computed(() => auth.employee?.role === 'super_admin');
const { toast }    = useToast();
const { confirm }  = useConfirm();

const backups      = ref([]);
const summary      = ref({});
const loading      = ref(false);
const creating     = ref(false);
const restoring    = ref(false);
const expanded     = ref(null);
const restoreTarget = ref(null);

function toggleExpand(filename) {
  expanded.value = expanded.value === filename ? null : filename;
}

function formatRelative(dateStr) {
  const date = new Date(dateStr);
  const now   = new Date();
  const diff  = Math.floor((now - date) / 1000);
  if (diff < 60)   return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

async function load() {
  loading.value = true;
  try {
    const res = await axios.get('/api/backups');
    backups.value = res.data.backups;
    summary.value = res.data.summary;
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to load backups.', 'error');
  } finally {
    loading.value = false;
  }
}

async function createBackup() {
  creating.value = true;
  try {
    const res = await axios.post('/api/backups');
    toast(
      `Backup created: ${res.data.filename} — ${res.data.total_records.toLocaleString()} records (${res.data.size})`
    );
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to create backup.', 'error');
  } finally {
    creating.value = false;
  }
}

async function download(filename) {
  try {
    const res = await axios.get(
      `/api/backups/${encodeURIComponent(filename)}/download`,
      { responseType: 'blob' }
    );
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/octet-stream' }));
    const a   = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Download failed. Please try again.'), 'error');
  }
}

function confirmRestore(backup) {
  restoreTarget.value = backup;
}

async function doRestore() {
  if (! restoreTarget.value) return;
  restoring.value = true;
  try {
    const res = await axios.post(`/api/backups/${encodeURIComponent(restoreTarget.value.filename)}/restore`);
    restoreTarget.value = null;
    toast(`Restore complete — ${res.data.statements} statements executed.`);
    await load();
  } catch (e) {
    restoreTarget.value = null;
    toast(e.response?.data?.message ?? 'Restore failed.', 'error');
  } finally {
    restoring.value = false;
  }
}

async function confirmDelete(filename) {
  const ok = await confirm({
    title:       `Delete backup "${filename}"?`,
    message:     'This cannot be undone.',
    confirmText: 'Delete',
  });
  if (!ok) return;
  try {
    await axios.delete(`/api/backups/${encodeURIComponent(filename)}`);
    toast('Backup deleted.');
    if (expanded.value === filename) expanded.value = null;
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Delete failed.', 'error');
  }
}

onMounted(load);
</script>

<style scoped>

/* ── Summary cards ───────────────────────────────────── */
.backup-stat-card {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  padding: 16px 20px; box-shadow: 0 1px 4px rgba(27, 42, 94,0.05);
}
.backup-stat-icon {
  width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}

/* ── Backup list items ───────────────────────────────── */
.backup-item {
  background: #fff; border: 1px solid #E4E9F2; border-radius: 12px;
  overflow: hidden; box-shadow: 0 1px 4px rgba(27, 42, 94,0.04);
  transition: box-shadow 0.15s;
}
.backup-item:hover { box-shadow: 0 4px 16px rgba(27, 42, 94,0.08); }

.backup-item-head {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; cursor: pointer;
}
.backup-item-head:hover { background: #F8FAFF; }

/* ── Detail expanded area ────────────────────────────── */
.backup-detail {
  border-top: 1px solid #EEF2FA; background: #F7F8FD; padding: 14px 18px;
  font-size: 12px; color: #64748B;
}

/* ── Action buttons ──────────────────────────────────── */
.btn-dl {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; background: #EEF2FF; color: #2E4A8C;
  font-size: 12px; font-weight: 600; border-radius: 7px;
  border: none; cursor: pointer; transition: all 0.15s;
}
.btn-dl:hover { background: #DDE5FF; }
.btn-restore {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; background: #FFF7E6; color: #B45309;
  font-size: 12px; font-weight: 600; border-radius: 7px;
  border: none; cursor: pointer; transition: all 0.15s;
}
.btn-restore:hover { background: #FEF3C7; }
.btn-del {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; background: #FEF2F2; color: #DC2626;
  font-size: 12px; font-weight: 600; border-radius: 7px;
  border: none; cursor: pointer; transition: all 0.15s;
}
.btn-del:hover { background: #FEE2E2; }

/* ── Mobile ──────────────────────────────────────────── */
@media (max-width: 640px) {
  .backup-item-head { flex-wrap: wrap; gap: 8px; }
  .max-w-screen-xl { padding-left: 12px; padding-right: 12px; }
}
</style>
