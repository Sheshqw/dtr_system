<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Reports" subtitle="Generate monthly DTR summaries and official CSC Form 48" :icon="FileTextIcon" />

    <div class="report-page flex-1 overflow-auto bg-[#F8FAFC] p-6">
      <div class="mx-auto space-y-5" :class="previewData ? 'max-w-5xl' : 'max-w-3xl'">

        <!-- ── Generator card ─────────────────────────────────────────── -->
        <div class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">

          <!-- Gradient header — matches the visual tier of Bulk Export and
               Month Lock below it; this is the primary card on the page,
               so it shouldn't look plainer than the secondary ones. -->
          <div class="bg-gradient-to-r from-[#1B2A5E] to-[#2E4A8C] px-6 py-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center flex-shrink-0">
              <FileTextIcon class="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 class="text-white font-semibold text-sm">CSC Form 48 — Daily Time Record</h2>
              <p class="text-white/60 text-xs mt-0.5">Official government-mandated DTR for SUC employees</p>
            </div>
          </div>

          <div class="bg-white px-6 py-5 space-y-4">

            <!-- Admin/HR: employee picker -->
            <template v-if="canSelectEmployee">
              <div>
                <label class="form-label">Employee</label>

                <!-- Skeleton while fetching employees -->
                <div v-if="loadingEmployees" class="space-y-2">
                  <div class="sk h-9 w-full rounded-md"></div>
                  <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-[#E2E8F0]">
                    <div class="sk w-7 h-7 rounded-full flex-shrink-0"></div>
                    <div class="flex-1 space-y-1.5">
                      <div class="sk h-3 w-36 rounded"></div>
                      <div class="sk h-2.5 w-24 rounded"></div>
                    </div>
                  </div>
                </div>

                <!-- Real input once loaded -->
                <template v-else>
                  <div class="relative">
                    <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#6B7A94]" />
                    <input
                      v-model="empSearch"
                      @focus="showDropdown = true"
                      @blur="hideDropdown"
                      class="form-input pl-9"
                      placeholder="Search employee name or ID…"
                      autocomplete="off"
                    />
                    <div
                      v-if="showDropdown && empResults.length"
                      class="absolute z-20 top-full left-0 right-0 mt-1 bg-white border border-[#E2E8F0] rounded-lg shadow-lg max-h-52 overflow-y-auto"
                    >
                      <button
                        v-for="emp in empResults"
                        :key="emp.id"
                        type="button"
                        @mousedown.prevent="selectEmp(emp)"
                        class="w-full text-left px-4 py-2.5 hover:bg-[#F8FAFC] flex items-center gap-3"
                      >
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#2E4A8C] to-[#3D5CA3] flex items-center justify-center flex-shrink-0">
                          <span class="text-white text-xs font-semibold">{{ initials(emp.name) }}</span>
                        </div>
                        <div>
                          <p class="text-sm font-medium text-[#0F172A]">{{ emp.name }}</p>
                          <p class="text-xs text-[#6B7A94]">{{ emp.employee_id }} · {{ emp.department ?? '—' }}</p>
                        </div>
                      </button>
                    </div>
                  </div>
                  <!-- Selected pill -->
                  <div v-if="selected" class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg" style="background:#EEF2FF; border:1px solid #C7D2F6;">
                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#2E4A8C] to-[#3D5CA3] flex items-center justify-center flex-shrink-0">
                      <span class="text-white text-xs font-semibold">{{ initials(selected.name) }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-[#0F172A]">{{ selected.name }}</p>
                      <p class="text-xs text-[#64748B]">{{ selected.position }} · {{ selected.department }}</p>
                    </div>
                    <button @click="selected = null; empSearch = ''; previewData = null" class="text-[#6B7A94] hover:text-[#EF4444]">
                      <XIcon class="w-4 h-4" />
                    </button>
                  </div>
                </template>
              </div>
            </template>

            <!-- Regular employee: own account -->
            <template v-else>
              <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[#C7D2F6]" style="background:#EEF2FF;">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#2E4A8C] to-[#3D5CA3] flex items-center justify-center flex-shrink-0">
                  <span class="text-white text-sm font-bold">{{ initials(auth.employee?.name ?? '') }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-[#0F172A]">{{ auth.employee?.name }}</p>
                  <p class="text-xs text-[#64748B]">{{ auth.employee?.position ?? 'Employee' }} · Generating your personal DTR</p>
                </div>
                <LockIcon class="w-4 h-4 text-[#2E4A8C] flex-shrink-0" />
              </div>
              <p class="text-xs text-[#6B7A94] -mt-1">You can preview your own DTR here. Printed or downloaded copies must be requested from HR or Admin.</p>
            </template>

            <!-- Month / Year -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Month</label>
                <select v-model="month" class="form-input">
                  <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </div>
              <div>
                <label class="form-label">Year</label>
                <select v-model="year" class="form-input">
                  <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3 pt-1">
              <button
                @click="loadPreview"
                :disabled="!effectiveEmployee || previewing"
                class="btn-primary inline-flex items-center gap-2"
              >
                <Loader2Icon v-if="previewing" class="w-4 h-4 animate-spin" />
                <EyeIcon v-else class="w-4 h-4" />
                Preview DTR
              </button>
              <template v-if="canSelectEmployee">
                <button
                  @click="download"
                  :disabled="!effectiveEmployee || downloading"
                  class="btn-outline inline-flex items-center gap-2"
                >
                  <Loader2Icon v-if="downloading" class="w-4 h-4 animate-spin" />
                  <DownloadIcon v-else class="w-4 h-4" />
                  Download PDF
                </button>
                <button
                  @click="exportCsv"
                  :disabled="!effectiveEmployee || exportingCsv"
                  class="btn-outline inline-flex items-center gap-2"
                >
                  <Loader2Icon v-if="exportingCsv" class="w-4 h-4 animate-spin" />
                  <TableIcon v-else class="w-4 h-4" />
                  Export CSV
                </button>
              </template>
            </div>
          </div>
        </div>

        <!-- ── Bulk Export card ───────────────────────────────────────── -->
        <div v-if="canSelectEmployee" class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">

          <!-- Gradient header -->
          <div class="bg-gradient-to-r from-[#064E3B] to-[#059669] px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center flex-shrink-0">
                <FileDownIcon class="w-5 h-5 text-white" />
              </div>
              <div>
                <h2 class="text-white font-semibold text-sm">Bulk Attendance Export</h2>
                <p class="text-white/60 text-xs mt-0.5">Export all employees' monthly attendance as a single CSV</p>
              </div>
            </div>
            <!-- Employee count badge -->
            <div class="flex items-center gap-2 bg-white/10 border border-white/20 rounded-lg px-3 py-1.5">
              <UsersIcon class="w-3.5 h-3.5 text-white/70" />
              <span class="text-white text-xs font-semibold">{{ bulkEmployeeCount }} employee{{ bulkEmployeeCount !== 1 ? 's' : '' }}</span>
            </div>
          </div>

          <div class="bg-white px-6 py-5 space-y-4">

            <!-- Period & filters grid -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="bulk-label">Month</label>
                <select v-model="bulkMonth" class="form-input">
                  <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </div>
              <div>
                <label class="bulk-label">Year</label>
                <select v-model="bulkYear" class="form-input">
                  <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
              <div>
                <label class="bulk-label">Department <span class="text-[#6B7A94] font-normal">(optional)</span></label>
                <select v-model="bulkDepartment" class="form-input">
                  <option value="">All Departments</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="bulk-label">Staff Type <span class="text-[#6B7A94] font-normal">(optional)</span></label>
                <select v-model="bulkStaffType" class="form-input">
                  <option value="">All Staff Types</option>
                  <option value="faculty">Faculty / Instructor</option>
                  <option value="administrative">Administrative Staff</option>
                  <option value="security">Security Guard</option>
                  <option value="custodial">Custodial / Utility</option>
                  <option value="maintenance">Maintenance</option>
                  <option value="laboratory">Lab Technician</option>
                  <option value="driver">Driver</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>

            <!-- What's included — 3 sections -->
            <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 space-y-3">
              <p class="text-[10px] font-bold uppercase tracking-widest text-[#6B7A94]">Report sections included in CSV</p>
              <div class="grid grid-cols-3 gap-2">
                <!-- Section 1 -->
                <div class="rounded-md border border-emerald-100 bg-emerald-50 p-2.5">
                  <p class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider mb-1.5">§1 Daily Records</p>
                  <ul class="space-y-0.5">
                    <li v-for="c in ['Date & Day','AM In / Out','PM In / Out','Hours Rendered','Tardiness (min)','Overtime (min)','Undertime (min)','Status']" :key="c"
                        class="flex items-center gap-1 text-[11px] text-emerald-800">
                      <CheckCircle2Icon class="w-2.5 h-2.5 flex-shrink-0 text-emerald-500" />{{ c }}
                    </li>
                  </ul>
                </div>
                <!-- Section 2 -->
                <div class="rounded-md border border-blue-100 bg-blue-50 p-2.5">
                  <p class="text-[10px] font-bold text-blue-700 uppercase tracking-wider mb-1.5">§2 Monthly Summary</p>
                  <ul class="space-y-0.5">
                    <li v-for="c in ['Working Days','Days Present','Days Late','Absent Days','Leave Days','Total Hours','Total OT (min)','Attendance Rate %']" :key="c"
                        class="flex items-center gap-1 text-[11px] text-blue-800">
                      <CheckCircle2Icon class="w-2.5 h-2.5 flex-shrink-0 text-blue-500" />{{ c }}
                    </li>
                  </ul>
                </div>
                <!-- Section 3 -->
                <div class="rounded-md border border-violet-100 bg-violet-50 p-2.5">
                  <p class="text-[10px] font-bold text-violet-700 uppercase tracking-wider mb-1.5">§3 Campus Totals</p>
                  <ul class="space-y-0.5">
                    <li v-for="c in ['Total Employees','Total Present Days','Total Absent Days','Total Hours Rendered','Total Tardiness','Total Overtime','Total Undertime','Campus Attendance Rate']" :key="c"
                        class="flex items-center gap-1 text-[11px] text-violet-800">
                      <CheckCircle2Icon class="w-2.5 h-2.5 flex-shrink-0 text-violet-500" />{{ c }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Export summary & action -->
            <div class="flex items-center justify-between gap-4 pt-1">
              <div class="text-xs text-[#64748B]">
                <span v-if="bulkEmployeeCount > 0">
                  Will export <strong class="text-[#0F172A]">{{ bulkEmployeeCount }}</strong> employee{{ bulkEmployeeCount !== 1 ? 's' : '' }}
                  for <strong class="text-[#0F172A]">{{ months.find(m => m.value === bulkMonth)?.label }} {{ bulkYear }}</strong>
                  <span v-if="bulkDepartment || bulkStaffType"> — filtered</span>
                </span>
                <span v-else class="text-amber-600">No employees match the selected filters.</span>
              </div>
              <button
                @click="exportBulk"
                :disabled="exportingBulk || bulkEmployeeCount === 0"
                class="flex-shrink-0 inline-flex items-center gap-2 px-5 py-2 bg-[#059669] hover:bg-[#047857] text-white text-sm font-semibold rounded-lg disabled:opacity-50 transition-colors shadow-sm"
              >
                <Loader2Icon v-if="exportingBulk" class="w-4 h-4 animate-spin" />
                <DownloadIcon v-else class="w-4 h-4" />
                {{ exportingBulk ? 'Generating…' : 'Download CSV' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ── Preview skeleton (while loading) ─────────────────────── -->
        <div v-if="previewing" class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_4px_rgba(0,0,0,0.08)] overflow-hidden">

          <!-- Header skeleton — mimics the dark gradient header -->
          <div class="bg-gradient-to-r from-[#1B2A5E] to-[#2E4A8C] px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="sk-dark w-11 h-11 rounded-xl"></div>
              <div class="space-y-2">
                <div class="sk-dark h-4 w-44 rounded"></div>
                <div class="sk-dark h-3 w-64 rounded opacity-60"></div>
              </div>
            </div>
            <div class="sk-dark h-5 w-24 rounded"></div>
          </div>

          <!-- Stat strip skeleton — 6 columns -->
          <div class="grid grid-cols-6 border-b border-[#F4F6FB]">
            <div v-for="i in 6" :key="i" class="flex items-center gap-2.5 px-4 py-3 border-r border-[#F4F6FB] last:border-r-0">
              <div class="sk w-8 h-8 rounded-lg flex-shrink-0"></div>
              <div class="space-y-1.5 flex-1">
                <div class="sk h-4 w-10 rounded"></div>
                <div class="sk h-2.5 w-16 rounded"></div>
              </div>
            </div>
          </div>

          <!-- Legend skeleton -->
          <div class="flex gap-5 px-5 py-2.5 border-b border-[#F4F6FB]">
            <div v-for="i in 6" :key="i" class="sk h-3 w-14 rounded"></div>
          </div>

          <!-- Table skeleton -->
          <div class="px-4 pt-3 pb-2 space-y-1.5">
            <!-- Table header -->
            <div class="sk h-8 w-full rounded-md"></div>
            <!-- Rows — alternate opacity for weekend-like feel -->
            <div
              v-for="i in 16"
              :key="i"
              class="sk h-7 w-full rounded"
              :class="i % 7 === 0 || i % 7 === 1 ? 'opacity-40' : ''"
            ></div>
          </div>

          <!-- Totals footer skeleton -->
          <div class="flex items-center gap-6 px-5 py-3 border-t border-[#F4F6FB]">
            <div v-for="i in 4" :key="i" class="flex items-center gap-3">
              <div class="sk h-3 w-24 rounded"></div>
              <div class="sk h-5 w-12 rounded"></div>
            </div>
          </div>

          <!-- Action footer skeleton -->
          <div class="flex items-center justify-between px-5 py-3 border-t border-[#F4F6FB] bg-[#F8FAFC]">
            <div class="sk h-4 w-48 rounded"></div>
            <div class="flex gap-2">
              <div class="sk h-8 w-24 rounded-lg"></div>
              <div class="sk h-8 w-24 rounded-lg"></div>
              <div class="sk h-8 w-36 rounded-lg"></div>
            </div>
          </div>
        </div>

        <!-- ── Preview panel ──────────────────────────────────────────── -->
        <div v-if="previewData" class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_4px_rgba(0,0,0,0.08)] overflow-hidden">

          <!-- Preview header -->
          <div class="preview-head">
            <div class="preview-head-inner">
              <div class="ph-brand">
                <div class="ph-initials">{{ initials(previewData.employee.name) }}</div>
                <div>
                  <h3 class="ph-name">{{ previewData.employee.name }}</h3>
                  <p class="ph-meta">
                    {{ previewData.employee.employee_id ?? '—' }}
                    <span class="ph-dot">·</span>
                    {{ previewData.employee.position ?? '—' }}
                    <span class="ph-dot">·</span>
                    {{ previewData.employee.department ?? '—' }}
                  </p>
                </div>
              </div>
              <div class="ph-right">
                <div class="ph-period">{{ previewData.month_label }}</div>
                <div class="ph-campus">{{ previewData.employee.campus ?? 'NEMSU' }}</div>
              </div>
            </div>
            <button @click="previewData = null" class="ph-close" title="Close preview">
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Summary stats -->
          <div class="stat-strip">
            <div v-for="s in previewStats" :key="s.label" class="stat-card">
              <div class="stat-icon" :style="{ background: s.iconBg }">
                <component :is="s.icon" class="w-3.5 h-3.5" :style="{ color: s.iconColor }" />
              </div>
              <div>
                <div class="stat-val" :style="{ color: s.color }">{{ s.value }}</div>
                <div class="stat-lbl">{{ s.label }}</div>
              </div>
            </div>
          </div>

          <!-- Legend -->
          <div class="legend-row">
            <span class="legend-item"><span class="legend-dot" style="background:#15803D;"></span>Present</span>
            <span class="legend-item"><span class="legend-dot" style="background:#D97706;"></span>Late</span>
            <span class="legend-item"><span class="legend-dot" style="background:#DC2626;"></span>Absent</span>
            <span class="legend-item"><span class="legend-dot" style="background:#3D5CA3;"></span>On Leave</span>
            <span class="legend-item"><span class="legend-dot" style="background:#0284C7;"></span>Holiday</span>
            <span class="legend-item"><span class="legend-dot" style="background:#CBD5E1;"></span>Weekend</span>
          </div>

          <!-- DTR table -->
          <div class="report-table-wrap overflow-x-auto">
            <table class="dtr-table">
              <thead>
                <tr>
                  <th class="dtr-th dtr-th-day">Day</th>
                  <th class="dtr-th">AM In</th>
                  <th class="dtr-th">AM Out</th>
                  <th class="dtr-th">PM In</th>
                  <th class="dtr-th">PM Out</th>
                  <th class="dtr-th">Hours</th>
                  <th class="dtr-th">Tardy</th>
                  <th class="dtr-th">OT</th>
                  <th class="dtr-th">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in fullMonthRows"
                  :key="row.day"
                  class="dtr-row"
                  :class="rowClass(row)"
                >
                  <td class="dtr-td dtr-td-day">
                    <span class="day-num">{{ row.day }}</span>
                    <span class="day-name">{{ row.dayName }}</span>
                  </td>
                  <td class="dtr-td dtr-td-time">{{ row.morning_in ?? (row.isWeekend ? '' : '—') }}</td>
                  <td class="dtr-td dtr-td-time">{{ row.morning_out ?? (row.isWeekend ? '' : '—') }}</td>
                  <td class="dtr-td dtr-td-time">{{ row.afternoon_in ?? (row.isWeekend ? '' : '—') }}</td>
                  <td class="dtr-td dtr-td-time">{{ row.afternoon_out ?? (row.isWeekend ? '' : '—') }}</td>
                  <td class="dtr-td dtr-td-mono">
                    <span v-if="row.hours > 0" class="hrs-val">{{ row.hours.toFixed(1) }}h</span>
                    <span v-else-if="!row.isWeekend" class="hrs-nil">—</span>
                  </td>
                  <td class="dtr-td dtr-td-mono">
                    <span v-if="row.tardiness_minutes > 0" class="tardy-val">{{ row.tardiness_minutes }}'</span>
                    <span v-else-if="!row.isWeekend" class="hrs-nil">—</span>
                  </td>
                  <td class="dtr-td dtr-td-mono">
                    <span v-if="row.overtime_minutes > 0" class="ot-val">{{ row.overtime_minutes }}'</span>
                    <span v-else-if="!row.isWeekend" class="hrs-nil">—</span>
                  </td>
                  <td class="dtr-td">
                    <StatusBadge v-if="row.status && row.status !== 'weekend'" :status="row.status" :corrected="row.is_corrected" />
                    <span v-else-if="row.isWeekend" class="wknd-label">REST</span>
                    <span v-else class="hrs-nil">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Footer totals row -->
          <div class="totals-footer">
            <div class="tf-group">
              <span class="tf-label">Total Days Present</span>
              <span class="tf-val tf-green">{{ previewData.totals.days_present }}</span>
            </div>
            <div class="tf-sep"></div>
            <div class="tf-group">
              <span class="tf-label">Total Hours Rendered</span>
              <span class="tf-val tf-blue">{{ previewData.totals.hours_rendered }}h</span>
            </div>
            <div class="tf-sep"></div>
            <div class="tf-group">
              <span class="tf-label">Total Tardiness</span>
              <span class="tf-val tf-amber">{{ previewData.totals.tardiness_minutes }} min</span>
            </div>
            <div class="tf-sep"></div>
            <div class="tf-group">
              <span class="tf-label">Total Overtime</span>
              <span class="tf-val tf-indigo">{{ previewData.totals.overtime_minutes }} min</span>
            </div>
          </div>

          <!-- Action footer -->
          <div class="action-footer">
            <span class="af-note">
              <CheckCircle2Icon class="w-3.5 h-3.5" />
              {{ previewData.totals.days_present }} days present out of {{ workdayCount }} workdays
            </span>
            <div v-if="canSelectEmployee" class="af-actions">
              <button @click="printDtr" :disabled="printing" class="btn-outline inline-flex items-center gap-2 disabled:opacity-60">
                <Loader2Icon v-if="printing" class="w-4 h-4 animate-spin" />
                <PrinterIcon v-else class="w-4 h-4" />
                {{ printing ? 'Preparing…' : 'Print DTR' }}
              </button>
              <button @click="exportCsv" :disabled="exportingCsv" class="btn-outline inline-flex items-center gap-2">
                <Loader2Icon v-if="exportingCsv" class="w-4 h-4 animate-spin" />
                <TableIcon v-else class="w-4 h-4" />
                Export CSV
              </button>
              <button @click="download" :disabled="downloading" class="btn-primary inline-flex items-center gap-2">
                <Loader2Icon v-if="downloading" class="w-4 h-4 animate-spin" />
                <DownloadIcon v-else class="w-4 h-4" />
                Download CSC Form 48
              </button>
            </div>
            <span v-else class="af-note">
              <LockIcon class="w-3.5 h-3.5" />
              Preview only — ask HR or Admin for a printed or downloaded copy.
            </span>
          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/composables/useApiError';
import StatusBadge from '@/components/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
  FileText      as FileTextIcon,
  Search        as SearchIcon,
  X             as XIcon,
  Eye           as EyeIcon,
  Download      as DownloadIcon,
  Loader2       as Loader2Icon,
  Lock          as LockIcon,
  Table2        as TableIcon,
  Printer       as PrinterIcon,
  CheckCircle2  as CheckCircle2Icon,
  CalendarDays  as CalendarDaysIcon,
  Clock         as ClockIcon,
  TrendingDown  as TrendingDownIcon,
  TrendingUp    as TrendingUpIcon,
  AlertCircle   as AlertCircleIcon,
  BookOpen      as BookOpenIcon,
  FileDown      as FileDownIcon,
  Users         as UsersIcon,
} from 'lucide-vue-next';

const auth = useAuthStore();
const route = useRoute();
const { toast } = useToast();

const isSuperAdmin = computed(() => auth.employee?.role === 'super_admin');

const canSelectEmployee = computed(() =>
  ['admin', 'hr_officer', 'super_admin'].includes(auth.employee?.role)
);

const effectiveEmployee = computed(() => {
  if (canSelectEmployee.value) return selected.value;
  return auth.employee
    ? { id: auth.employee.id, name: auth.employee.name, position: auth.employee.position, department: auth.employee.department }
    : null;
});

const employees         = ref([]);
const loadingEmployees  = ref(false);
const empSearch    = ref('');
const showDropdown = ref(false);
const selected     = ref(null);
const previewData  = ref(null);
const previewing   = ref(false);
const downloading  = ref(false);
const printing     = ref(false);
const exportingCsv  = ref(false);
const exportingBulk = ref(false);

const now = new Date();
const month = ref(now.getMonth() + 1);
const year  = ref(now.getFullYear());
const bulkMonth      = ref(now.getMonth() + 1);
const bulkYear       = ref(now.getFullYear());
const bulkDepartment = ref('');
const bulkStaffType  = ref('');
const departments    = ref([]);


const bulkEmployeeCount = computed(() => {
  let list = employees.value;
  if (bulkDepartment.value) list = list.filter(e => e.department_id == bulkDepartment.value);
  if (bulkStaffType.value)  list = list.filter(e => e.staff_type === bulkStaffType.value);
  return list.length;
});

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' },
  { value: 3, label: 'March' },   { value: 4, label: 'April' },
  { value: 5, label: 'May' },     { value: 6, label: 'June' },
  { value: 7, label: 'July' },    { value: 8, label: 'August' },
  { value: 9, label: 'September'},{ value: 10, label: 'October' },
  { value: 11, label: 'November'},{ value: 12, label: 'December' },
];
const years = Array.from({ length: 6 }, (_, i) => now.getFullYear() - i);

const empResults = computed(() => {
  if (!empSearch.value) return employees.value.slice(0, 8);
  const s = empSearch.value.toLowerCase();
  return employees.value
    .filter(e => e.name.toLowerCase().includes(s) || e.employee_id.toLowerCase().includes(s))
    .slice(0, 8);
});

// ── Full month rows (all days, including blanks) ──────────────────
const fullMonthRows = computed(() => {
  if (!previewData.value) return [];
  const y = year.value;
  const m = month.value;
  const daysInMonth = new Date(y, m, 0).getDate();
  const recordMap = {};
  for (const r of previewData.value.records) recordMap[r.day] = r;

  const rows = [];
  for (let d = 1; d <= daysInMonth; d++) {
    const date    = new Date(y, m - 1, d);
    const dow     = date.getDay();
    const isWeekend = dow === 0 || dow === 6;
    const dayName = date.toLocaleDateString('en-PH', { weekday: 'short' });
    const rec     = recordMap[d];

    rows.push({
      day:               d,
      dayName,
      isWeekend,
      status:            rec?.status ?? (isWeekend ? 'weekend' : null),
      morning_in:        rec?.morning_in   ?? null,
      morning_out:       rec?.morning_out  ?? null,
      afternoon_in:      rec?.afternoon_in ?? null,
      afternoon_out:     rec?.afternoon_out ?? null,
      hours:             rec?.hours        ?? 0,
      tardiness_minutes: rec?.tardiness_minutes ?? 0,
      overtime_minutes:  rec?.overtime_minutes  ?? 0,
      is_corrected:      rec?.is_corrected ?? false,
    });
  }
  return rows;
});

const workdayCount = computed(() => fullMonthRows.value.filter(r => !r.isWeekend).length);

const previewStats = computed(() => {
  if (!previewData.value) return [];
  const t = previewData.value.totals;
  const absent = workdayCount.value - t.days_present - (t.leave_days ?? 0);
  return [
    { label: 'Days Present',   value: t.days_present,             color: '#15803D', iconBg: '#F0FDF4', iconColor: '#15803D', icon: CalendarDaysIcon },
    { label: 'Absent Days',    value: Math.max(0, absent),        color: '#DC2626', iconBg: '#FEF2F2', iconColor: '#DC2626', icon: AlertCircleIcon  },
    { label: 'Leave Days',     value: t.leave_days ?? 0,          color: '#3D5CA3', iconBg: '#EEF2FF', iconColor: '#3D5CA3', icon: BookOpenIcon     },
    { label: 'Tardiness (min)',value: t.tardiness_minutes,        color: '#D97706', iconBg: '#FFFBEB', iconColor: '#D97706', icon: TrendingDownIcon },
    { label: 'Overtime (min)', value: t.overtime_minutes,         color: '#0284C7', iconBg: '#F0F9FF', iconColor: '#0284C7', icon: TrendingUpIcon   },
    { label: 'Hours Rendered', value: t.hours_rendered + 'h',     color: '#2E4A8C', iconBg: '#EEF2FF', iconColor: '#2E4A8C', icon: ClockIcon        },
  ];
});

function rowClass(row) {
  if (row.isWeekend)              return 'dtr-row--weekend';
  if (row.status === 'absent')    return 'dtr-row--absent';
  if (row.status === 'late')      return 'dtr-row--late';
  if (row.status === 'holiday')   return 'dtr-row--holiday';
  if (row.status === 'on_leave')  return 'dtr-row--leave';
  if (row.status === 'present')   return 'dtr-row--present';
  return '';
}

function initials(name) {
  return (name ?? '').split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function selectEmp(emp) {
  selected.value     = emp;
  empSearch.value    = '';
  showDropdown.value = false;
  previewData.value  = null;
}

function hideDropdown() {
  setTimeout(() => { showDropdown.value = false; }, 150);
}

// ── API actions ───────────────────────────────────────────────────
async function loadPreview() {
  if (!effectiveEmployee.value) return;
  previewing.value  = true;
  previewData.value = null;
  try {
    const res = await axios.get(`/api/reports/preview/${effectiveEmployee.value.id}`, {
      params: { month: month.value, year: year.value },
    });
    previewData.value = res.data;
  } catch (err) {
    toast(err.response?.data?.message ?? 'Failed to load preview.', 'error');
  } finally {
    previewing.value = false;
  }
}

async function download() {
  if (!effectiveEmployee.value || !canSelectEmployee.value) return;
  downloading.value = true;
  try {
    const res = await axios.get(`/api/reports/form48/${effectiveEmployee.value.id}`, {
      params: { month: month.value, year: year.value },
      responseType: 'blob',
    });
    const contentType = res.headers?.['content-type'] ?? '';
    if (!contentType.includes('pdf')) {
      toast(await extractErrorMessage({ response: { data: res.data } }, 'Failed to generate PDF.'), 'error');
      return;
    }
    const url  = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
    const link = document.createElement('a');
    link.href  = url;
    const empId = canSelectEmployee.value ? selected.value?.employee_id : auth.employee?.employee_id;
    link.setAttribute('download', `DTR_${empId ?? 'employee'}_${year.value}_${String(month.value).padStart(2,'0')}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to generate PDF.'), 'error');
  } finally {
    downloading.value = false;
  }
}

async function exportCsv() {
  if (!effectiveEmployee.value || !canSelectEmployee.value) return;
  exportingCsv.value = true;
  try {
    const res = await axios.get(`/api/reports/export/${effectiveEmployee.value.id}`, {
      params: { month: month.value, year: year.value },
      responseType: 'blob',
    });
    const contentType = res.headers?.['content-type'] ?? '';
    if (!contentType.includes('csv')) {
      toast(await extractErrorMessage({ response: { data: res.data } }, 'Failed to export CSV.'), 'error');
      return;
    }
    const url  = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }));
    const link = document.createElement('a');
    link.href  = url;
    const empId = canSelectEmployee.value ? selected.value?.employee_id : auth.employee?.employee_id;
    link.setAttribute('download', `DTR_${empId ?? 'employee'}_${year.value}_${String(month.value).padStart(2,'0')}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to export CSV.'), 'error');
  } finally {
    exportingCsv.value = false;
  }
}

async function exportBulk() {
  exportingBulk.value = true;
  try {
    const params = { month: bulkMonth.value, year: bulkYear.value };
    if (bulkDepartment.value) params.department_id = bulkDepartment.value;
    if (bulkStaffType.value)  params.staff_type    = bulkStaffType.value;
    const res = await axios.get('/api/reports/export-bulk', { params, responseType: 'blob' });
    const contentType = res.headers?.['content-type'] ?? '';
    if (!contentType.includes('csv')) {
      toast(await extractErrorMessage({ response: { data: res.data } }, 'Failed to export bulk CSV.'), 'error');
      return;
    }
    const url  = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }));
    const link = document.createElement('a');
    link.href  = url;
    link.setAttribute('download', `Attendance_All_${bulkYear.value}_${String(bulkMonth.value).padStart(2,'0')}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to export bulk CSV.'), 'error');
  } finally {
    exportingBulk.value = false;
  }
}

// ── Print DTR ─────────────────────────────────────────────────────
function printDtr() {
  if (!previewData.value || printing.value || !canSelectEmployee.value) return;
  printing.value = true;

  const d  = previewData.value;
  const t  = d.totals;
  const absents = Math.max(0, workdayCount.value - t.days_present - (t.leave_days ?? 0));

  const fmt = (v) => v ? String(v).slice(0, 5) : '';

  const statusLabel = (s) => ({ present:'Present', late:'Late', absent:'Absent', on_leave:'On Leave', holiday:'Holiday', weekend:'' })[s] ?? '';
  const statusDot   = (s) => ({
    present:  'background:#15803D',
    late:     'background:#D97706',
    absent:   'background:#DC2626',
    on_leave: 'background:#3D5CA3',
    holiday:  'background:#0284C7',
  })[s] ?? '';
  const rowStyle = (r) => {
    if (r.isWeekend)             return 'background:#F5F7FA; color:#6B7A94;';
    if (r.status === 'absent')   return 'background:#FEF2F2;';
    if (r.status === 'late')     return 'background:#FFFBEB;';
    if (r.status === 'holiday')  return 'background:#F0F9FF;';
    if (r.status === 'on_leave') return 'background:#EEF2FF;';
    return '';
  };

  const tableRows = fullMonthRows.value.map(r => {
    const blank = r.isWeekend ? '' : '&mdash;';
    const dot   = !r.isWeekend && r.status
      ? `<span style="display:inline-block;width:7px;height:7px;border-radius:50%;vertical-align:middle;margin-right:3px;${statusDot(r.status)}"></span>`
      : '';
    return `<tr style="${rowStyle(r)}">
      <td class="td-day">${r.day}<span class="dn">${r.dayName}</span></td>
      <td>${fmt(r.morning_in)   || blank}</td>
      <td>${fmt(r.morning_out)  || blank}</td>
      <td>${fmt(r.afternoon_in) || blank}</td>
      <td>${fmt(r.afternoon_out)|| blank}</td>
      <td class="num">${r.hours > 0 ? r.hours.toFixed(2) : (r.isWeekend ? '' : '&mdash;')}</td>
      <td class="num red">${r.tardiness_minutes > 0 ? r.tardiness_minutes : (r.isWeekend ? '' : '&mdash;')}</td>
      <td class="num blue">${r.overtime_minutes  > 0 ? r.overtime_minutes  : (r.isWeekend ? '' : '&mdash;')}</td>
      <td style="text-align:left;">${dot}${statusLabel(r.status)}</td>
    </tr>`;
  }).join('');

  const generatedOn = new Date().toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>DTR &mdash; ${d.employee.name} &mdash; ${d.month_label}</title>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family: 'Times New Roman', Times, serif;
      font-size: 10.5pt;
      color: #0F172A;
      background: #fff;
    }

    @page {
      size: Letter portrait;
      margin: 14mm 14mm 12mm;
    }

    /* ── Screen wrapper ───────────────── */
    @media screen {
      body { background: #E5E7EB; padding: 24px; }
      .page { max-width: 780px; margin: 0 auto; background: #fff; padding: 28px 32px; box-shadow: 0 4px 24px rgba(0,0,0,.15); border-radius: 4px; }
    }
    @media print {
      body { background: #fff; }
      .page { padding: 0; }
      .no-print { display: none !important; }
    }

    /* ── Government header ────────────── */
    .gov-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 10px;
      border-bottom: 2.5pt solid #24356F;
      margin-bottom: 10px;
    }
    .gh-center { text-align: center; flex: 1; }
    .gh-republic { font-size: 8pt; letter-spacing: .12em; text-transform: uppercase; color: #475569; }
    .gh-uni { font-size: 13pt; font-weight: 700; color: #24356F; margin: 2px 0; letter-spacing: .01em; }
    .gh-campus { font-size: 9pt; color: #475569; }
    .gh-badge {
      background: #24356F;
      color: #C9A227;
      font-size: 7.5pt;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      padding: 5px 10px;
      border-radius: 3px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* ── Title bar ────────────────────── */
    .title-bar {
      text-align: center;
      margin-bottom: 10px;
    }
    .title-bar h1 { font-size: 13pt; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #24356F; }
    .title-bar p { font-size: 8pt; color: #64748B; margin-top: 2px; }

    /* ── Employee info ────────────────── */
    .emp-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4px 28px;
      padding: 9px 12px;
      border: 1pt solid #CBD5E1;
      border-left: 3pt solid #24356F;
      background: #F8FAFC;
      margin-bottom: 10px;
    }
    .ef { display: flex; gap: 6px; align-items: baseline; }
    .el { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .09em; color: #64748B; width: 78px; flex-shrink: 0; }
    .ev { font-size: 10pt; font-weight: 600; border-bottom: 1pt solid #94A3B8; flex: 1; padding-bottom: 1px; }

    /* ── Summary stats ────────────────── */
    .stats {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 5px;
      margin-bottom: 10px;
    }
    .stat { text-align: center; border: 1pt solid #E2E8F0; border-radius: 4px; padding: 6px 4px; }
    .sv { font-size: 15pt; font-weight: 700; line-height: 1; }
    .sl { font-size: 7pt; text-transform: uppercase; letter-spacing: .07em; color: #64748B; margin-top: 3px; }

    /* ── Attendance table ─────────────── */
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 10px; }
    thead th {
      background: #24356F;
      color: #fff;
      padding: 5px 5px;
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      text-align: center;
      border: 1pt solid #0A1550;
    }
    thead th:first-child { text-align: left; padding-left: 7px; }
    td {
      padding: 3pt 5pt;
      text-align: center;
      border-bottom: .5pt solid #E2E8F0;
      border-right: .5pt solid #E2E8F0;
      font-size: 9pt;
    }
    td:first-child { border-left: .5pt solid #E2E8F0; }
    .td-day { text-align: left; font-weight: 700; white-space: nowrap; padding-left: 7px; }
    .dn { font-size: 7.5pt; font-weight: 400; color: #6B7A94; margin-left: 4px; }
    .num { font-family: 'Courier New', monospace; font-size: 9pt; }
    .red  { color: #DC2626; }
    .blue { color: #0284C7; }

    /* ── Certification ────────────────── */
    .certif {
      font-size: 9pt;
      text-align: justify;
      line-height: 1.65;
      padding: 8px 10px;
      background: #FAFAFA;
      border-left: 2pt solid #C9A227;
      margin-bottom: 14px;
      font-style: italic;
    }

    /* ── Signature area ───────────────── */
    .sig-area {
      display: grid;
      grid-template-columns: 1fr 60px 1fr;
      margin-top: 6px;
    }
    .sig-col { text-align: center; }
    .sig-label { font-size: 8pt; color: #64748B; margin-bottom: 26px; }
    .sig-line { border-top: 1pt solid #0F172A; padding-top: 4px; }
    .sig-name { font-size: 9.5pt; font-weight: 700; text-transform: uppercase; }
    .sig-role { font-size: 8pt; color: #475569; margin-top: 1px; }

    /* ── Footer ───────────────────────── */
    .footer {
      margin-top: 10px;
      border-top: .5pt solid #CBD5E1;
      padding-top: 6px;
      display: flex;
      justify-content: space-between;
      font-size: 7.5pt;
      color: #6B7A94;
    }

    /* ── Screen-only print button ─────── */
    .print-btn {
      display: block;
      margin: 20px auto 0;
      padding: 10px 28px;
      background: #24356F;
      color: #fff;
      font-size: 11pt;
      font-weight: 700;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      letter-spacing: .04em;
    }
    @media print { .print-btn { display: none; } }
  </style>
</head>
<body>
<div class="page">

  <!-- Government header -->
  <div class="gov-header">
    <div style="width:48px;"></div>
    <div class="gh-center">
      <div class="gh-republic">Republic of the Philippines</div>
      <div class="gh-uni">Northern Eastern Mindanao State University</div>
      <div class="gh-campus">${d.employee.campus ?? 'Campus'} &nbsp;·&nbsp; Human Resources Management Office</div>
    </div>
    <div class="gh-badge">CSC Form 48</div>
  </div>

  <!-- Title -->
  <div class="title-bar">
    <h1>Daily Time Record</h1>
    <p>Period: ${d.month_label} &nbsp;&bull;&nbsp; Official Hours of Work</p>
  </div>

  <!-- Employee info -->
  <div class="emp-grid">
    <div class="ef"><span class="el">Employee Name</span><span class="ev">${d.employee.name}</span></div>
    <div class="ef"><span class="el">Employee ID</span><span class="ev">${d.employee.employee_id ?? '&mdash;'}</span></div>
    <div class="ef"><span class="el">Position</span><span class="ev">${d.employee.position ?? '&mdash;'}</span></div>
    <div class="ef"><span class="el">Department</span><span class="ev">${d.employee.department ?? '&mdash;'}</span></div>
  </div>

  <!-- Summary stats -->
  <div class="stats">
    <div class="stat"><div class="sv" style="color:#15803D">${t.days_present}</div><div class="sl">Days Present</div></div>
    <div class="stat"><div class="sv" style="color:#DC2626">${absents}</div><div class="sl">Absent Days</div></div>
    <div class="stat"><div class="sv" style="color:#3D5CA3">${t.leave_days ?? 0}</div><div class="sl">Leave Days</div></div>
    <div class="stat"><div class="sv" style="color:#D97706">${t.tardiness_minutes}</div><div class="sl">Tardy (min)</div></div>
    <div class="stat"><div class="sv" style="color:#0284C7">${t.overtime_minutes}</div><div class="sl">OT (min)</div></div>
    <div class="stat"><div class="sv" style="color:#24356F">${parseFloat(t.hours_rendered).toFixed(1)}</div><div class="sl">Hours</div></div>
  </div>

  <!-- Attendance table -->
  <table>
    <thead>
      <tr>
        <th style="width:64px; text-align:left; padding-left:7px;">Day</th>
        <th>AM In</th>
        <th>AM Out</th>
        <th>PM In</th>
        <th>PM Out</th>
        <th>Hours</th>
        <th>Tardy</th>
        <th>OT</th>
        <th style="text-align:left; width:88px;">Status</th>
      </tr>
    </thead>
    <tbody>${tableRows}</tbody>
  </table>

  <!-- Certification text -->
  <div class="certif">
    &emsp;<strong>I CERTIFY</strong> on my honor that the above is a true and correct report of the hours of work
    performed, record of which was made daily at the time of arrival at and departure from office.
  </div>

  <!-- Signatures -->
  <div class="sig-area">
    <div class="sig-col">
      <div class="sig-label">Employee's Signature Over Printed Name</div>
      <div class="sig-line">
        <div class="sig-name">${d.employee.name}</div>
        <div class="sig-role">${d.employee.position ?? 'Employee'}</div>
      </div>
    </div>
    <div></div>
    <div class="sig-col">
      <div class="sig-label">Verified as to the Prescribed Office Hours</div>
      <div class="sig-line">
        <div class="sig-name">&nbsp;</div>
        <div class="sig-role">Campus Director / Authorized Representative</div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <span>NEMSU UDTRMS &bull; Human Resources Management Office</span>
    <span>Generated: ${generatedOn}</span>
  </div>

  <!-- Screen-only print button -->
  <button class="print-btn no-print" onclick="window.print()">&#128438; Print This Document</button>

</div>
</body>
</html>`;

  // Inject a hidden iframe — no popup, no blocker
  const iframe = document.createElement('iframe');
  iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;border:none;visibility:hidden;';
  document.body.appendChild(iframe);

  iframe.addEventListener('load', () => {
    try {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
    } finally {
      printing.value = false;
    }
    // Clean up the iframe after the print dialog is dismissed
    const cleanup = () => {
      document.body.removeChild(iframe);
      iframe.contentWindow?.removeEventListener('afterprint', cleanup);
    };
    iframe.contentWindow.addEventListener('afterprint', cleanup);
    // Fallback removal if afterprint never fires (some browsers)
    setTimeout(cleanup, 60_000);
  });

  iframe.srcdoc = html;
}

onMounted(async () => {
  if (canSelectEmployee.value) {
    loadingEmployees.value = true;
    try {
      const [empRes, deptRes] = await Promise.all([
        axios.get('/api/employees'),
        axios.get('/api/departments'),
      ]);
      employees.value   = empRes.data;
      departments.value = deptRes.data;

      // Pre-select employee if navigated from Employees page (Quick Form 48)
      if (route.query.employee) {
        const preselect = employees.value.find(e => e.employee_id === route.query.employee);
        if (preselect) selected.value = preselect;
      }
    } catch { /* ignore */ } finally {
      loadingEmployees.value = false;
    }
  }
});
</script>

<style scoped>
@reference "../css/app.css";

.form-label { @apply block text-sm font-medium text-[#0F172A] mb-1.5; }
.form-input  { @apply w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-md text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] bg-white; }
.btn-primary { @apply px-4 py-2 bg-[#2E4A8C] text-white text-sm font-medium rounded-lg hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors; }
.btn-outline  { @apply px-4 py-2 text-sm font-medium border border-[#E2E8F0] rounded-lg text-[#374151] hover:bg-[#F0F4FF] transition-colors disabled:opacity-60; }
.bulk-label  { @apply block text-xs font-semibold text-[#374151] mb-1.5 uppercase tracking-wide; }

/* ── Preview header ────────────────────────────────── */
.preview-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #F4F6FB;
  background: linear-gradient(135deg, #1B2A5E 0%, #2E4A8C 100%);
  gap: 12px;
}
.preview-head-inner { display: flex; align-items: center; justify-content: space-between; flex: 1; gap: 16px; flex-wrap: wrap; }
.ph-brand    { display: flex; align-items: center; gap: 12px; }
.ph-initials {
  width: 44px; height: 44px; border-radius: 10px;
  background: rgba(201, 162, 39,0.2); border: 1.5px solid rgba(201, 162, 39,0.5);
  color: #C9A227; font-size: 14px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ph-name { font-size: 15px; font-weight: 700; color: #fff; line-height: 1.2; }
.ph-meta { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 2px; }
.ph-dot  { margin: 0 4px; }
.ph-right { text-align: right; }
.ph-period { font-size: 13px; font-weight: 700; color: #C9A227; }
.ph-campus { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 2px; letter-spacing: 0.05em; text-transform: uppercase; }
.ph-close  { color: rgba(255,255,255,0.4); padding: 4px; border-radius: 6px; transition: all 0.15s; background: transparent; flex-shrink: 0; }
.ph-close:hover { color: #fff; background: rgba(255,255,255,0.1); }

/* ── Summary stat strip ──────────────────────────── */
.stat-strip {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  border-bottom: 1px solid #F4F6FB;
}
.stat-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-right: 1px solid #F4F6FB;
}
.stat-card:last-child { border-right: none; }
.stat-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-val { font-size: 17px; font-weight: 700; line-height: 1; }
.stat-lbl { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #6B7A94; margin-top: 2px; }

/* ── Legend ──────────────────────────────────────── */
.legend-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px 20px;
  background: #FAFBFD;
  border-bottom: 1px solid #F4F6FB;
  flex-wrap: wrap;
}
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 10.5px; color: #64748B; }
.legend-dot  { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

/* ── DTR table ───────────────────────────────────── */
.dtr-table { width: 100%; border-collapse: collapse; font-size: 12px; }

.dtr-th {
  padding: 8px 10px;
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #64748B;
  background: #F4F7FC;
  border-bottom: 1px solid #E2E8F0;
  text-align: center;
  white-space: nowrap;
}
.dtr-th-day { text-align: left; width: 72px; }

.dtr-row { border-bottom: 1px solid #F1F5F9; transition: background 0.1s; }
.dtr-row:hover { filter: brightness(0.97); }
.dtr-row:last-child { border-bottom: none; }

.dtr-row--weekend { background: #F8FAFC; }
.dtr-row--present { background: #fff; }
.dtr-row--late    { background: #FFFBEB; }
.dtr-row--absent  { background: #FEF2F2; }
.dtr-row--holiday { background: #F0F9FF; }
.dtr-row--leave   { background: #EEF2FF; }

.dtr-td { padding: 7px 10px; text-align: center; vertical-align: middle; }
.dtr-td-day  { display: flex; align-items: center; gap: 6px; text-align: left; }
.dtr-td-time { font-family: 'Courier New', monospace; font-size: 11px; color: #475569; }
.dtr-td-mono { font-family: 'Courier New', monospace; font-size: 11px; }

.day-num  { font-size: 12px; font-weight: 700; color: #0F172A; width: 22px; text-align: right; flex-shrink: 0; }
.day-name { font-size: 9.5px; color: #6B7A94; font-weight: 500; }
.dtr-row--weekend .day-num  { color: #6B7A94; }

.hrs-val   { font-weight: 600; color: #0F172A; }
.hrs-nil   { color: #94A3B8; }
.tardy-val { font-weight: 600; color: #D97706; }
.ot-val    { font-weight: 600; color: #0284C7; }
.wknd-label { font-size: 9px; font-weight: 700; color: #94A3B8; letter-spacing: 0.05em; }

/* ── Totals footer ───────────────────────────────── */
.totals-footer {
  display: flex;
  align-items: center;
  gap: 0;
  padding: 12px 20px;
  background: #F4F7FC;
  border-top: 2px solid #E2E8F0;
  flex-wrap: wrap;
  gap: 12px;
}
.tf-group { display: flex; flex-direction: column; gap: 1px; }
.tf-sep   { width: 1px; height: 28px; background: #DDE4EE; flex-shrink: 0; }
.tf-label { font-size: 9.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #6B7A94; }
.tf-val   { font-size: 16px; font-weight: 700; line-height: 1; }
.tf-green  { color: #15803D; }
.tf-blue   { color: #2E4A8C; }
.tf-amber  { color: #D97706; }
.tf-indigo { color: #3D5CA3; }

/* ── Action footer ───────────────────────────────── */
.action-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 20px;
  border-top: 1px solid #F4F6FB;
  background: #fff;
  flex-wrap: wrap;
}
.af-note {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748B;
}
.af-note svg { color: #15803D; flex-shrink: 0; }
.af-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── Responsive ──────────────────────────────────── */
@media (max-width: 900px) {
  .stat-strip { grid-template-columns: repeat(3, 1fr); }
  .stat-card:nth-child(3) { border-right: none; }
}
@media (max-width: 640px) {
  .stat-strip { grid-template-columns: repeat(2, 1fr); }
  .stat-card:nth-child(2) { border-right: none; }
  .totals-footer { flex-direction: column; align-items: flex-start; }
  .tf-sep { display: none; }
  .action-footer { flex-direction: column; align-items: flex-start; gap: 8px; }

  /* Filter panel: stack vertically */
  .report-filters { flex-direction: column !important; align-items: stretch !important; gap: 8px; padding: 12px; }
  .report-filters > * { width: 100%; }
  .report-filters select,
  .report-filters input { width: 100% !important; }

  /* Table horizontal scroll */
  .report-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .dtr-table { min-width: 640px; }

  /* Outer padding */
  .report-page { padding: 10px 12px !important; }

  /* Stats */
  .stat-card { padding: 12px 10px; }
  .stat-val  { font-size: 20px; }
  .stat-lbl  { font-size: 9px; }

  /* Print button full width */
  .print-btn { width: 100%; justify-content: center; }
}

/* ── Skeleton shimmer ──────────────────────────── */
@keyframes sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.sk {
  background: linear-gradient(90deg, #F4F6FB 25%, #DDE3F0 50%, #F4F6FB 75%);
  background-size: 200% 100%;
  animation: sk-shimmer 1.6s ease-in-out infinite;
  border-radius: 4px;
}

/* Dark variant — for use on the dark gradient header */
.sk-dark {
  background: linear-gradient(90deg, rgba(255,255,255,.12) 25%, rgba(255,255,255,.22) 50%, rgba(255,255,255,.12) 75%);
  background-size: 200% 100%;
  animation: sk-shimmer 1.6s ease-in-out infinite;
  border-radius: 4px;
}

/* ══════════════════════════════════════════════════════════════════
   PRINT — CSC Form 48 layout
   ══════════════════════════════════════════════════════════════════ */
@media print {
  /* Hide everything that isn't the DTR preview panel */
  :global(body > *:not(#app))           { display: none !important; }
  :global(aside), :global(header),
  :global(.fixed), :global(.sticky)     { display: none !important; }

  /* Reset page background */
  :global(body), :global(#app),
  :global(main), .flex.flex-col         { background: #fff !important; }

  /* Show only the preview panel, fill the page */
  .preview-panel {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Hide generator card, bulk export card, all buttons */
  .preview-panel ~ *,
  button, .btn-primary, .btn-outline,
  .ph-header, .stat-strip, .legend-row { display: none !important; }

  /* Show the preview panel actions bar & legend for context */
  .ph-name, .ph-month                  { display: block !important; }

  /* DTR table — full width, proper borders */
  .dtr-table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 9.5pt !important;
    page-break-inside: avoid;
  }
  .dtr-th, .dtr-td {
    border: 1px solid #555 !important;
    padding: 3pt 5pt !important;
    color: #000 !important;
    background: #fff !important;
  }
  .dtr-th { font-weight: 700 !important; background: #f5f5f5 !important; }

  /* Status row colors — subtle for print */
  .dtr-row--present  td { border-left: 3pt solid #16a34a !important; }
  .dtr-row--late     td { border-left: 3pt solid #d97706 !important; }
  .dtr-row--absent   td { border-left: 3pt solid #dc2626 !important; }
  .dtr-row--weekend  td { background: #f9f9f9 !important; color: #888 !important; }
  .dtr-row--holiday  td { background: #fdf9e7 !important; }
  .dtr-row--leave    td { background: #eef2ff !important; }

  /* Totals footer */
  .totals-footer {
    border: 1px solid #ccc !important;
    font-size: 9pt !important;
    page-break-inside: avoid;
  }

  /* Print header — NEMSU seal + employee name + period */
  .ph-row { page-break-inside: avoid; }

  /* Page margins */
  @page { margin: 15mm 12mm 15mm 15mm; size: A4 portrait; }
}
</style>
