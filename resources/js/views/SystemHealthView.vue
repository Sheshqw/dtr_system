<template>
  <div class="flex flex-col h-full" style="background: #F4F6FB;">

    <PageHeader title="System Health" subtitle="Error monitoring and system integrity scanner" :icon="HeartPulseIcon">
      <button
        @click="runScan"
        :disabled="scanning"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors disabled:opacity-50 ph-btn-primary"
      >
        <Loader2Icon v-if="scanning" class="w-4 h-4 animate-spin" />
        <ScanSearchIcon v-else class="w-4 h-4" />
        {{ scanning ? 'Scanning…' : 'Run Scanner' }}
      </button>
    </PageHeader>

    <div class="flex-1 overflow-y-auto">
      <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="stat-tile" style="--stat-color:#DC2626;--stat-glow:rgba(220,38,38,0.28);">
            <div class="stat-tile-icon">
              <ShieldAlertIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="sh-stat-label">Critical <span class="sh-stat-tag">new</span></p>
              <p class="stat-tile-value">{{ summary.critical ?? 0 }}</p>
            </div>
          </div>
          <div class="stat-tile" style="--stat-color:#D97706;--stat-glow:rgba(217,119,6,0.28);">
            <div class="stat-tile-icon">
              <AlertTriangleIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="sh-stat-label">Errors &amp; Warnings <span class="sh-stat-tag">new</span></p>
              <p class="stat-tile-value">{{ summary.warnings ?? 0 }}</p>
            </div>
          </div>
          <div class="stat-tile" style="--stat-color:#6D28D9;--stat-glow:rgba(109,40,217,0.28);">
            <div class="stat-tile-icon">
              <EyeIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="sh-stat-label">Acknowledged</p>
              <p class="stat-tile-value">{{ summary.acknowledged ?? 0 }}</p>
            </div>
          </div>
          <div class="stat-tile" style="--stat-color:#059669;--stat-glow:rgba(5,150,105,0.28);">
            <div class="stat-tile-icon">
              <CircleCheckBigIcon class="w-4 h-4" />
            </div>
            <div>
              <p class="sh-stat-label">Resolved</p>
              <p class="stat-tile-value">{{ summary.resolved ?? 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-xl px-4 py-3 flex flex-wrap items-center gap-3" style="border:1px solid #E5E7EB;">
          <div class="relative flex-1 min-w-[200px]">
            <SearchIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2" style="color:#6B7A94;" />
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search message, file…"
              class="sh-filter-input w-full pl-9"
            />
          </div>
          <select v-model="filters.severity" class="sh-filter-input">
            <option value="">All Severities</option>
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
          <select v-model="filters.status" class="sh-filter-input">
            <option value="">All Statuses</option>
            <option value="new">New</option>
            <option value="acknowledged">Acknowledged</option>
            <option value="resolved">Resolved</option>
          </select>
          <select v-model="filters.type" class="sh-filter-input">
            <option value="">All Types</option>
            <option v-for="t in types" :key="t" :value="t">{{ typeLabel(t) }}</option>
          </select>
          <button @click="resetFilters" class="px-3 py-1.5 text-sm font-medium border border-[#E4E9F2] rounded-lg text-[#64748B] hover:bg-[#F4F6FB] transition-colors bg-white">
            Reset
          </button>
        </div>

        <!-- Bulk action bar -->
        <div class="flex flex-wrap items-center gap-2">
          <template v-if="selected.size > 0">
            <p class="text-xs font-semibold mr-1" style="color:#374151;">{{ selected.size }} selected</p>
            <button @click="bulkAction('acknowledge')" class="sh-bulk-btn" style="background:#F5F3FF;color:#6D28D9;border:1px solid #DDD6FE;">
              <EyeIcon class="w-3.5 h-3.5" /> Acknowledge
            </button>
            <button @click="bulkAction('resolve')" class="sh-bulk-btn" style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;">
              <CircleCheckBigIcon class="w-3.5 h-3.5" /> Resolve
            </button>
            <button @click="bulkDelete" class="sh-bulk-btn" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;">
              <Trash2Icon class="w-3.5 h-3.5" /> Delete
            </button>
            <button @click="selected.clear()" class="text-xs font-medium ml-1" style="color:#6B7A94;">Clear selection</button>
          </template>
          <template v-else>
            <button
              @click="clearResolved"
              :disabled="!summary.resolved"
              class="sh-bulk-btn disabled:opacity-40 disabled:cursor-not-allowed"
              style="background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;"
            >
              <ArchiveIcon class="w-3.5 h-3.5" /> Clear Resolved ({{ summary.resolved ?? 0 }})
            </button>
            <button @click="clearAll" class="sh-bulk-btn" style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;">
              <Trash2Icon class="w-3.5 h-3.5" /> Clear All Logs
            </button>
          </template>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 6" :key="i" class="bg-white rounded-lg border border-[#E2E8F0] px-4 py-3 animate-pulse flex gap-4">
            <div class="h-3 bg-slate-200 rounded w-32"></div>
            <div class="h-3 bg-slate-100 rounded flex-1"></div>
            <div class="h-3 bg-slate-100 rounded w-16"></div>
          </div>
        </div>

        <!-- Table -->
        <div v-else class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">
          <EmptyState
            v-if="logs.length === 0"
            title="No issues found"
            description="Nothing matches your current filters, or your system is squeaky clean. Try Run Scanner to check for integrity issues."
          />
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr style="background:#FAFAFA;border-bottom:1px solid #F1F5F9;">
                  <th class="px-4 py-3 w-9">
                    <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectAll" class="sh-checkbox" aria-label="Select all" />
                  </th>
                  <th class="text-left px-3 py-3 sh-th">Time</th>
                  <th class="text-left px-3 py-3 sh-th">Severity</th>
                  <th class="text-left px-3 py-3 sh-th">Type</th>
                  <th class="text-left px-3 py-3 sh-th">Message</th>
                  <th class="text-center px-3 py-3 sh-th">Status</th>
                  <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="log in logs" :key="log.id">
                  <tr
                    class="sh-row"
                    role="button"
                    tabindex="0"
                    :aria-expanded="expanded === log.id"
                    :title="`${expanded === log.id ? 'Collapse' : 'Expand'} details for log ${log.id}`"
                    @click="toggleExpand(log.id)"
                    @keydown.enter="toggleExpand(log.id)"
                    @keydown.space.prevent="toggleExpand(log.id)"
                  >
                    <td class="px-4 py-3" @click.stop>
                      <input type="checkbox" :checked="selected.has(log.id)" @change="toggleSelect(log.id)" class="sh-checkbox" :aria-label="`Select log ${log.id}`" />
                    </td>
                    <td class="px-3 py-3 font-mono text-xs whitespace-nowrap" style="color:#64748B;">{{ log.last_seen_at ?? log.created_at }}</td>
                    <td class="px-3 py-3">
                      <span class="sh-badge" :class="severityClass(log.severity)">{{ log.severity }}</span>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#64748B;">{{ typeLabel(log.type) }}</td>
                    <td class="px-3 py-3 max-w-[380px]">
                      <p class="text-xs font-medium truncate" style="color:#0F172A;" :title="log.message">{{ log.message }}</p>
                      <p v-if="log.file" class="text-[11px] font-mono mt-0.5 truncate" style="color:#6B7A94;">
                        {{ log.file }}<span v-if="log.line">:{{ log.line }}</span>
                        <span v-if="log.occurrences > 1" class="sh-occ-tag">×{{ log.occurrences }}</span>
                      </p>
                    </td>
                    <td class="px-3 py-3 text-center">
                      <span class="sh-badge" :class="statusClass(log.status)">{{ statusLabel(log.status) }}</span>
                    </td>
                    <td class="px-4 py-3" @click.stop>
                      <div class="flex items-center justify-end gap-1.5">
                        <button v-if="log.status !== 'acknowledged'" @click="act(log, 'acknowledge')" class="sh-icon-btn" style="color:#6D28D9;" title="Acknowledge">
                          <EyeIcon class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="log.status !== 'resolved'" @click="act(log, 'resolve')" class="sh-icon-btn" style="color:#059669;" title="Resolve">
                          <CircleCheckBigIcon class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="log.status === 'resolved'" @click="act(log, 'reopen')" class="sh-icon-btn" style="color:#D97706;" title="Reopen">
                          <Undo2Icon class="w-3.5 h-3.5" />
                        </button>
                        <button @click="removeOne(log)" class="sh-icon-btn" style="color:#DC2626;" title="Delete">
                          <Trash2Icon class="w-3.5 h-3.5" />
                        </button>
                        <ChevronDownIcon class="w-4 h-4 transition-transform duration-200 ml-1" :class="expanded === log.id ? 'rotate-180' : ''" style="color:#6B7A94;" />
                      </div>
                    </td>
                  </tr>

                  <tr v-if="expanded === log.id" style="background:#F8FAFC;border-bottom:1px solid #F1F5F9;">
                    <td colspan="7" class="px-6 py-4 space-y-3">
                      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div><span style="color:#6B7A94;">First seen</span><p class="font-mono mt-0.5" style="color:#0F172A;">{{ log.created_at }}</p></div>
                        <div><span style="color:#6B7A94;">Last seen</span><p class="font-mono mt-0.5" style="color:#0F172A;">{{ log.last_seen_at ?? '—' }}</p></div>
                        <div><span style="color:#6B7A94;">Acknowledged by</span><p class="mt-0.5" style="color:#0F172A;">{{ log.acknowledged_by ?? '—' }}</p></div>
                        <div><span style="color:#6B7A94;">Resolved by</span><p class="mt-0.5" style="color:#0F172A;">{{ log.resolved_by ?? '—' }}</p></div>
                      </div>
                      <div v-if="log.trace">
                        <p class="text-[11px] font-semibold uppercase tracking-wider mb-1.5" style="color:#6B7A94;">Stack Trace</p>
                        <pre class="text-[11px] font-mono p-3 rounded-lg overflow-x-auto" style="background:#0F172A;color:#CBD5E1;max-height:220px;">{{ log.trace }}</pre>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <div class="px-4" v-if="meta">
            <PaginationBar
              :current-page="meta.current_page"
              :last-page="meta.last_page"
              :total="meta.total"
              :from="meta.from"
              :to="meta.to"
              @go="load"
            />
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { useDebounce } from '@/composables/useDebounce';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import {
  HeartPulse    as HeartPulseIcon,
  ScanSearch    as ScanSearchIcon,
  Loader2       as Loader2Icon,
  Search        as SearchIcon,
  ShieldAlert   as ShieldAlertIcon,
  AlertTriangle as AlertTriangleIcon,
  CircleCheckBig as CircleCheckBigIcon,
  Eye           as EyeIcon,
  Archive       as ArchiveIcon,
  Trash2        as Trash2Icon,
  Undo2         as Undo2Icon,
  ChevronDown   as ChevronDownIcon,
} from 'lucide-vue-next';

const logs    = ref([]);
const meta    = ref(null);
const types   = ref([]);
const summary = reactive({ critical: 0, warnings: 0, acknowledged: 0, resolved: 0 });
const loading = ref(false);
const scanning = ref(false);
const expanded  = ref(null);
const selected  = reactive(new Set());

const filters = reactive({ search: '', severity: '', status: '', type: '' });
const debouncedSearch = useDebounce(computed(() => filters.search));

const { toast }   = useToast();
const { confirm } = useConfirm();

const TYPE_LABELS = {
  exception:       'Exception',
  db_integrity:    'DB Integrity',
  data_integrity:  'Data Integrity',
};
function typeLabel(t) { return TYPE_LABELS[t] ?? (t ?? '').replace(/_/g, ' '); }

const STATUS_LABELS = { new: 'New', acknowledged: 'Acknowledged', resolved: 'Resolved' };
function statusLabel(s) { return STATUS_LABELS[s] ?? s; }

function severityClass(sev) {
  return {
    critical: 'sh-badge--critical',
    warning:  'sh-badge--warning',
    info:     'sh-badge--info',
  }[sev] ?? 'sh-badge--info';
}
function statusClass(status) {
  return {
    new:          'sh-badge--new',
    acknowledged: 'sh-badge--acknowledged',
    resolved:     'sh-badge--resolved',
  }[status] ?? 'sh-badge--new';
}

function toggleExpand(id) {
  expanded.value = expanded.value === id ? null : id;
}

const allOnPageSelected = computed(() => logs.value.length > 0 && logs.value.every(l => selected.has(l.id)));
function toggleSelectAll() {
  if (allOnPageSelected.value) {
    logs.value.forEach(l => selected.delete(l.id));
  } else {
    logs.value.forEach(l => selected.add(l.id));
  }
}
function toggleSelect(id) {
  selected.has(id) ? selected.delete(id) : selected.add(id);
}

async function load(page = 1) {
  loading.value = true;
  try {
    const res = await axios.get('/api/system-health', {
      params: { page, ...filters },
    });
    logs.value = res.data.data;
    meta.value  = res.data.meta;
    types.value = res.data.types ?? [];
    Object.assign(summary, res.data.summary);
    selected.clear();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to load system health logs.', 'error');
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.search = '';
  filters.severity = '';
  filters.status = '';
  filters.type = '';
}

watch([debouncedSearch, () => filters.severity, () => filters.status, () => filters.type], () => load(1));

async function runScan() {
  scanning.value = true;
  try {
    const res = await axios.post('/api/system-health/scan');
    toast(res.data.message);
    await load(1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Scan failed.', 'error');
  } finally {
    scanning.value = false;
  }
}

async function act(log, action) {
  try {
    await axios.post(`/api/system-health/${log.id}/${action}`);
    await load(meta.value?.current_page ?? 1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Action failed.', 'error');
  }
}

async function removeOne(log) {
  const ok = await confirm({ title: 'Delete this log entry?', confirmText: 'Delete' });
  if (!ok) return;
  try {
    await axios.delete(`/api/system-health/${log.id}`);
    if (expanded.value === log.id) expanded.value = null;
    await load(meta.value?.current_page ?? 1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Delete failed.', 'error');
  }
}

async function bulkAction(action) {
  const ids = [...selected];
  try {
    await Promise.all(ids.map(id => axios.post(`/api/system-health/${id}/${action}`)));
    toast(`${ids.length} log(s) ${action === 'acknowledge' ? 'acknowledged' : 'resolved'}.`);
    await load(meta.value?.current_page ?? 1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Some items could not be updated.', 'error');
  }
}

async function bulkDelete() {
  const ids = [...selected];
  const ok = await confirm({
    title:       `Delete ${ids.length} selected log(s)?`,
    message:     'This cannot be undone.',
    confirmText: 'Delete',
  });
  if (!ok) return;
  try {
    await Promise.all(ids.map(id => axios.delete(`/api/system-health/${id}`)));
    toast(`${ids.length} log(s) deleted.`);
    await load(1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Some items could not be deleted.', 'error');
  }
}

async function clearResolved() {
  try {
    const res = await axios.post('/api/system-health/clear-resolved');
    toast(res.data.message);
    await load(1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to clear resolved logs.', 'error');
  }
}

async function clearAll() {
  const ok = await confirm({
    title:       'Clear all logs?',
    message:     'This permanently deletes every health log entry, including unresolved ones. This cannot be undone.',
    confirmText: 'Clear All',
  });
  if (!ok) return;
  try {
    const res = await axios.delete('/api/system-health');
    toast(res.data.message);
    await load(1);
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to clear logs.', 'error');
  }
}

onMounted(() => load(1));
</script>

<style scoped>
/* ── Summary cards (icon/value/wrap now come from the shared .stat-tile
     classes in app.css; only the label + "new" tag stay page-specific) ── */
.sh-stat-label { font-size: 11px; font-weight: 500; color: #6B7A94; display: flex; align-items: center; gap: 5px; }
.sh-stat-tag {
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  color: #6B7A94; background: #F1F5F9; padding: 1px 5px; border-radius: 4px;
}

/* ── Filter bar ──────────────────────────────────────── */
.sh-filter-input {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 7px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.sh-filter-input:focus { outline: none; border-color: #2E4A8C; box-shadow: 0 0 0 3px rgba(46, 74, 140,0.08); }

/* ── Bulk action buttons ─────────────────────────────── */
.sh-bulk-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 13px; font-size: 12.5px; font-weight: 600; border-radius: 8px;
  transition: filter 0.15s;
}
.sh-bulk-btn:hover:not(:disabled) { filter: brightness(0.97); }

/* ── Table ───────────────────────────────────────────── */
.sh-th { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6B7A94; }
.sh-row { border-bottom: 1px solid #F9FAFB; cursor: pointer; transition: background 0.12s; }
.sh-row:hover { background: #FAFAFA; }
.sh-checkbox { width: 15px; height: 15px; border-radius: 4px; accent-color: #2E4A8C; cursor: pointer; }

.sh-occ-tag {
  display: inline-block; margin-left: 6px; padding: 0 5px; border-radius: 4px;
  background: #EEF2FF; color: #4338CA; font-weight: 700; font-size: 10px;
}

/* ── Badges ──────────────────────────────────────────── */
.sh-badge {
  display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px;
  font-size: 10.5px; font-weight: 700; text-transform: capitalize; letter-spacing: 0.01em;
  border: 1px solid transparent;
}
.sh-badge--critical     { background: #FEF2F2; color: #991B1B; border-color: #FECACA; }
.sh-badge--warning      { background: #FFFBEB; color: #92400E; border-color: #FDE68A; }
.sh-badge--info         { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.sh-badge--new          { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.sh-badge--acknowledged { background: #F5F3FF; color: #6D28D9; border-color: #DDD6FE; }
.sh-badge--resolved     { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }

:global([data-theme="dark"]) .sh-badge--critical     { background: rgba(220,38,38,0.14)  !important; color: #FCA5A5 !important; border-color: rgba(239,68,68,0.28)  !important; }
:global([data-theme="dark"]) .sh-badge--warning      { background: rgba(217,119,6,0.14)  !important; color: #FCD34D !important; border-color: rgba(217,119,6,0.28)  !important; }
:global([data-theme="dark"]) .sh-badge--info,
:global([data-theme="dark"]) .sh-badge--new          { background: rgba(59,130,246,0.14) !important; color: #93C5FD !important; border-color: rgba(59,130,246,0.28) !important; }
:global([data-theme="dark"]) .sh-badge--acknowledged { background: rgba(147,51,234,0.14) !important; color: #C4B5FD !important; border-color: rgba(147,51,234,0.28) !important; }
:global([data-theme="dark"]) .sh-badge--resolved     { background: rgba(16,185,129,0.14) !important; color: #6EE7B7 !important; border-color: rgba(16,185,129,0.28) !important; }

/* ── Row action icon buttons ─────────────────────────── */
.sh-icon-btn {
  width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: transparent; transition: background 0.15s;
}
.sh-icon-btn:hover { background: #F1F5F9; }

/* ── Mobile ──────────────────────────────────────────── */
@media (max-width: 640px) {
  .max-w-screen-xl { padding-left: 12px; padding-right: 12px; }
}
</style>
