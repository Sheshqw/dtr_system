<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Reports" subtitle="Generate monthly DTR summaries and official CSC Form 48" :icon="FileTextIcon" />

    <div class="report-page flex-1 overflow-auto bg-[#F8FAFC] p-6">
      <div class="mx-auto" :class="(activeTab === 'generate' && previewData) ? 'max-w-5xl' : 'max-w-3xl'">

        <!-- ── Tab bar ─────────────────────────────────────────────────── -->
        <div class="tab-bar">
          <button
            v-for="t in reportTabs" :key="t.key"
            type="button"
            @click="activeTab = t.key"
            class="tab-btn"
            :class="activeTab === t.key ? 'tab-btn--active' : ''"
          >
            <component :is="t.icon" class="w-4 h-4" />
            {{ t.label }}
            <span v-if="t.badge" class="tab-badge">{{ t.badge }}</span>
            <span v-else-if="t.dot" class="tab-dot" title="Ready to download"></span>
          </button>
        </div>

        <div class="space-y-5">

        <!-- ── Generator card ─────────────────────────────────────────── -->
        <template v-if="activeTab === 'generate'">
        <div class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">

          <!-- Card header — tinted strip + colored accent, not a solid fill,
               so a page of several cards doesn't read as heavy/formal. -->
          <div class="card-head" style="--accent:#1B2A5E; --tint:#F4F6FB;">
            <div class="card-head-left">
              <div class="card-head-icon">
                <FileTextIcon class="w-5 h-5" />
              </div>
              <div>
                <h2 class="card-head-title">CSC Form 48 — Daily Time Record</h2>
                <p class="card-head-sub">Official government-mandated DTR for SUC employees</p>
              </div>
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
                      <p class="text-sm font-medium text-[#0F172A] flex items-center gap-2 flex-wrap">
                        {{ selected.name }}
                        <span v-if="selected.semi_monthly_dtr" class="emp-status-pill">{{ employmentLabel(selected.employment_status) }}</span>
                      </p>
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
                  <p class="text-sm font-semibold text-[#0F172A] flex items-center gap-2 flex-wrap">
                    {{ auth.employee?.name }}
                    <span v-if="selfSemiMonthly" class="emp-status-pill">Semi-monthly DTR</span>
                  </p>
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

            <!-- DTR coverage — Contractual (COS) / Job Order employees print one DTR per half month -->
            <Transition name="coverage">
              <DtrPeriodPicker
                v-if="isSemiMonthly"
                v-model="period"
                :month="month"
                :year="year"
                :consents="canSelectEmployee ? null : myConsents"
              />
            </Transition>

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
              <button
                v-if="canSelectEmployee || canDownloadOwn"
                @click="download"
                :disabled="!effectiveEmployee || downloading"
                class="btn-outline inline-flex items-center gap-2"
              >
                <Loader2Icon v-if="downloading" class="w-4 h-4 animate-spin" />
                <DownloadIcon v-else class="w-4 h-4" />
                Download PDF
              </button>
              <button
                v-if="canSelectEmployee"
                @click="exportCsv"
                :disabled="!effectiveEmployee || exportingCsv"
                class="btn-outline inline-flex items-center gap-2"
              >
                <Loader2Icon v-if="exportingCsv" class="w-4 h-4 animate-spin" />
                <TableIcon v-else class="w-4 h-4" />
                Export CSV
              </button>
            </div>
          </div>
        </div>
        </template>

        <!-- ── Print Permission card (employee self-service) ───────────── -->
        <template v-if="activeTab === 'requests'">
        <div v-if="!canSelectEmployee" class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">
          <div class="card-head" style="--accent:#1B2A5E; --tint:#F4F6FB;">
            <div class="card-head-left">
              <div class="card-head-icon">
                <ShieldCheckIcon class="w-5 h-5" />
              </div>
              <div>
                <h2 class="card-head-title">Print Permission — {{ selectedPeriodLabel }}</h2>
                <p class="card-head-sub">Get HR/Admin sign-off to download an official, printable copy</p>
              </div>
            </div>
          </div>

          <div class="bg-white px-6 py-5 space-y-3">
            <!-- Semi-monthly staff request each half separately — switch here and
                 see every span's permission status at a glance. -->
            <DtrPeriodPicker
              v-if="isSemiMonthly"
              v-model="period"
              :month="month"
              :year="year"
              :consents="myConsents"
              compact
            />

            <!-- No request yet (or previous one was denied/used) -->
            <template v-if="!currentPeriodConsent || currentPeriodConsent.status === 'denied'">
              <p v-if="currentPeriodConsent?.status === 'denied'" class="text-sm text-[#B91C1C] bg-[#FEF2F2] border border-[#FECACA] rounded-lg px-3 py-2">
                Your last request for this period was denied<span v-if="currentPeriodConsent.reviewer_remarks">: “{{ currentPeriodConsent.reviewer_remarks }}”</span>. You may request again below.
              </p>
              <p class="text-sm text-[#475569]">
                To download or print your official DTR for <strong>{{ selectedPeriodLabel }}</strong>, submit a request — HR or Admin must approve it first.
              </p>
              <input
                v-model="consentPurpose"
                maxlength="255"
                class="form-input"
                placeholder="Purpose (optional) — e.g. scholarship requirement"
              />
              <button
                @click="requestConsent"
                :disabled="requestingConsent"
                class="btn-primary inline-flex items-center gap-2"
              >
                <Loader2Icon v-if="requestingConsent" class="w-4 h-4 animate-spin" />
                <SendIcon v-else class="w-4 h-4" />
                {{ currentPeriodConsent?.status === 'denied' ? 'Request Again' : 'Request Print Permission' }}
              </button>
            </template>

            <!-- Pending review -->
            <template v-else-if="currentPeriodConsent.status === 'pending'">
              <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[#FDE68A]" style="background:#FFFBEB;">
                <ClockIcon class="w-5 h-5 text-[#D97706] flex-shrink-0" />
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-[#92400E]">Pending HR/Admin review</p>
                  <p class="text-xs text-[#92400E]/80">Submitted {{ currentPeriodConsent.submitted_at }}</p>
                </div>
              </div>
            </template>

            <!-- Approved, not yet used -->
            <template v-else-if="currentPeriodConsent.status === 'approved' && !currentPeriodConsent.used_at">
              <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[#BBF7D0]" style="background:#F0FDF4;">
                <CheckCircle2Icon class="w-5 h-5 text-[#15803D] flex-shrink-0" />
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-[#15803D]">Approved — you may download your DTR once</p>
                  <p class="text-xs text-[#15803D]/80">Use the Download PDF button above. Reviewed {{ currentPeriodConsent.reviewed_at }}.</p>
                </div>
              </div>
            </template>

            <!-- Approved and already used -->
            <template v-else>
              <div class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[#E2E8F0]" style="background:#F8FAFC;">
                <CheckCircle2Icon class="w-5 h-5 text-[#64748B] flex-shrink-0" />
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-[#334155]">Already downloaded {{ currentPeriodConsent.used_at }}</p>
                  <p class="text-xs text-[#64748B]">Need another copy? Submit a new request.</p>
                </div>
              </div>
              <button
                @click="requestConsent"
                :disabled="requestingConsent"
                class="btn-outline inline-flex items-center gap-2"
              >
                <Loader2Icon v-if="requestingConsent" class="w-4 h-4 animate-spin" />
                <SendIcon v-else class="w-4 h-4" />
                Request Another Copy
              </button>
            </template>
          </div>
        </div>

        <!-- ── DTR Print Requests (HR/Admin review queue) ───────────────── -->
        <div v-if="canSelectEmployee" class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">
          <div class="card-head" style="--accent:#1B2A5E; --tint:#F4F6FB;">
            <div class="card-head-left">
              <div class="card-head-icon">
                <ShieldCheckIcon class="w-5 h-5" />
              </div>
              <div>
                <h2 class="card-head-title flex items-center gap-2">
                  DTR Print Requests
                  <span class="live-indicator" title="Updates automatically — no reload needed">
                    <span class="live-dot"></span>Live
                  </span>
                </h2>
                <p class="card-head-sub">Approve before an employee can download their official DTR</p>
              </div>
            </div>
            <span v-if="pendingConsents.length" class="text-xs font-bold text-white bg-[#EF4444] rounded-full px-2.5 py-1">
              {{ pendingConsents.length }} pending
            </span>
          </div>

          <div class="bg-white px-6 py-5 space-y-4">

            <!-- My Digital Signature ─────────────────────────────────── -->
            <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg border border-[#E2E8F0] flex-wrap" style="background:#FAFBFD;">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-lg border border-[#E2E8F0] bg-white flex items-center justify-center flex-shrink-0 overflow-hidden">
                  <img v-if="mySignatureUrl" :src="mySignatureUrl" class="max-w-full max-h-full object-contain" alt="Your signature" />
                  <PenLineIcon v-else class="w-5 h-5 text-[#94A3B8]" />
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-[#0F172A]">My Digital Signature</p>
                  <p class="text-xs text-[#6B7A94]">
                    <span v-if="!mySignatureUrl">Upload one to sign DTRs you approve</span>
                    <span v-else-if="mySignatureEnabled">Shown on DTRs you approve for download</span>
                    <span v-else>Uploaded, but hidden from downloaded DTRs</span>
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-3 flex-shrink-0">
                <label v-if="mySignatureUrl" class="inline-flex items-center gap-2 cursor-pointer select-none">
                  <span class="text-xs font-medium text-[#475569]">{{ mySignatureEnabled ? 'Included' : 'Hidden' }}</span>
                  <span
                    class="relative inline-block w-9 h-5 rounded-full transition-colors"
                    :style="{ background: mySignatureEnabled ? '#15803D' : '#CBD5E1', opacity: togglingSignature ? 0.6 : 1 }"
                    @click="!togglingSignature && toggleSignature(!mySignatureEnabled)"
                  >
                    <span
                      class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform"
                      :style="{ transform: mySignatureEnabled ? 'translateX(16px)' : 'translateX(0)' }"
                    ></span>
                  </span>
                </label>
                <label class="btn-outline text-xs cursor-pointer inline-flex items-center gap-1.5 !py-1.5">
                  <Loader2Icon v-if="uploadingSignature" class="w-3.5 h-3.5 animate-spin" />
                  <UploadIcon v-else class="w-3.5 h-3.5" />
                  {{ mySignatureUrl ? 'Replace' : 'Upload' }}
                  <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" :disabled="uploadingSignature" @change="onSignatureSelected" />
                </label>
                <button v-if="mySignatureUrl" @click="removeSignature" :disabled="uploadingSignature" class="text-xs text-[#B91C1C] hover:underline">Remove</button>
              </div>
            </div>

            <!-- Pending queue ──────────────────────────────────────────── -->
            <div v-if="!pendingConsents.length" class="empty-pending">
              <CheckCircle2Icon class="w-5 h-5" />
              All caught up — no pending print requests.
            </div>

            <div
              v-for="c in pendingConsents" :key="c.id"
              class="pending-row flex items-start justify-between gap-3 px-4 py-3 rounded-lg border border-[#E2E8F0] flex-wrap"
              :class="{ 'pending-row--new': justArrivedIds.has(c.id) }"
            >
              <div class="flex items-start gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#2E4A8C] to-[#3D5CA3] flex items-center justify-center flex-shrink-0">
                  <span class="text-white text-xs font-semibold">{{ initials(c.employee.name) }}</span>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-[#0F172A] truncate">{{ c.employee.name }}</p>
                  <p class="text-xs text-[#6B7A94] mt-0.5">
                    {{ c.employee.employee_id }}<span v-if="c.employee.position"> · {{ c.employee.position }}</span><span v-if="c.employee.department"> · {{ c.employee.department }}</span>
                  </p>
                  <p class="text-xs text-[#6B7A94] mt-1">
                    {{ c.month_label }}<span v-if="isSuperAdmin && c.employee.campus"> · {{ c.employee.campus }}</span> · submitted {{ c.submitted_at }}
                  </p>
                  <p v-if="c.purpose" class="text-xs text-[#475569] mt-1 italic">“{{ c.purpose }}”</p>

                  <div v-if="denyingId === c.id" class="mt-2 flex items-center gap-2 flex-wrap">
                    <input v-model="denyRemarks" maxlength="500" class="form-input" placeholder="Reason for denial (optional)" />
                    <button
                      @click="denyConsent(c)"
                      :disabled="reviewingId === c.id"
                      class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap disabled:opacity-60"
                      style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;"
                    >Confirm Deny</button>
                    <button @click="denyingId = null" class="text-xs text-[#6B7A94] hover:text-[#0F172A]">Cancel</button>
                  </div>
                </div>
              </div>

              <div v-if="denyingId !== c.id" class="flex items-center gap-2 flex-shrink-0">
                <button
                  @click="approveConsent(c)"
                  :disabled="reviewingId === c.id"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors disabled:opacity-60"
                  style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;"
                >
                  <Loader2Icon v-if="reviewingId === c.id" class="w-3.5 h-3.5 animate-spin" />
                  <CheckCircle2Icon v-else class="w-3.5 h-3.5" />
                  Approve
                </button>
                <button
                  @click="denyingId = c.id; denyRemarks = ''"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                  style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;"
                >
                  <XCircleIcon class="w-3.5 h-3.5" />
                  Deny
                </button>
              </div>
            </div>

            <!-- Request History (collapsed by default) ────────────────────── -->
            <div v-if="recentConsentDecisions.length" class="history-section">
              <button
                type="button"
                @click="showConsentHistory = !showConsentHistory"
                class="history-toggle"
              >
                <span class="history-toggle-left">
                  <HistoryIcon class="w-4 h-4" />
                  Request History
                  <span class="history-count">{{ recentConsentDecisions.length }}</span>
                </span>
                <ChevronDownIcon class="w-4 h-4 chevron" :class="showConsentHistory ? 'chevron--open' : ''" />
              </button>

              <div class="history-collapse" :class="showConsentHistory ? 'is-open' : ''">
                <div class="history-collapse-inner">
                  <div class="history-list">
                    <div
                      v-for="c in recentConsentDecisions" :key="c.id"
                      class="history-row"
                      :class="{ 'history-row--new': justArrivedIds.has(c.id) }"
                    >
                      <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#2E4A8C] to-[#3D5CA3] flex items-center justify-center flex-shrink-0">
                          <span class="text-white text-[10px] font-semibold">{{ initials(c.employee.name) }}</span>
                        </div>
                        <div class="min-w-0">
                          <p class="text-xs font-semibold text-[#0F172A] truncate">{{ c.employee.name }}</p>
                          <p class="text-[11px] text-[#6B7A94]">{{ c.month_label }} · {{ c.reviewed_at }}</p>
                        </div>
                      </div>
                      <span
                        class="text-[10px] font-bold uppercase tracking-wide rounded-full px-2 py-0.5 flex-shrink-0"
                        :style="c.status === 'approved' ? 'background:#ECFDF5;color:#065F46;' : 'background:#FEF2F2;color:#991B1B;'"
                      >{{ c.status }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        </template>

        <!-- ── Bulk Export card ───────────────────────────────────────── -->
        <template v-if="activeTab === 'bulk' && canSelectEmployee">
        <div v-if="canSelectEmployee" class="rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.08)] overflow-hidden border border-[#E2E8F0]">

          <!-- Card header -->
          <div class="card-head" style="--accent:#059669; --tint:#F0FDF4;">
            <div class="card-head-left">
              <div class="card-head-icon">
                <FileDownIcon class="w-5 h-5" />
              </div>
              <div>
                <h2 class="card-head-title">Bulk Attendance Export</h2>
                <p class="card-head-sub">Export all employees' monthly attendance as a single CSV</p>
              </div>
            </div>
            <!-- Employee count badge -->
            <div class="flex items-center gap-2 bg-[#059669]/10 border border-[#059669]/25 rounded-lg px-3 py-1.5">
              <UsersIcon class="w-3.5 h-3.5 text-[#059669]" />
              <span class="text-[#065F46] text-xs font-semibold">{{ bulkEmployeeCount }} employee{{ bulkEmployeeCount !== 1 ? 's' : '' }}</span>
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
        </template>

        <!-- ── Preview skeleton (while loading) ─────────────────────── -->
        <template v-if="activeTab === 'generate'">
        <div v-if="previewing" class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_4px_rgba(0,0,0,0.08)] overflow-hidden">

          <!-- Header skeleton — mimics the document letterhead it loads into -->
          <div class="px-5 py-4 flex items-center justify-between border-b border-[#E2E8F0]" style="background:#FAFBFD;">
            <div class="flex items-center gap-3">
              <div class="sk w-11 h-11 rounded-xl"></div>
              <div class="space-y-2">
                <div class="sk h-4 w-44 rounded"></div>
                <div class="sk h-3 w-64 rounded opacity-60"></div>
              </div>
            </div>
            <div class="sk h-5 w-24 rounded"></div>
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
        <div v-if="previewData" class="bg-white border border-[#E2E8F0] rounded-xl shadow-[0_1px_4px_rgba(0,0,0,0.08)] overflow-hidden doc-preview">

          <button @click="previewData = null" class="ph-close" title="Close preview">
            <XIcon class="w-4 h-4" />
          </button>

          <!-- Government letterhead — mirrors the official CSC Form 48 -->
          <div class="doc-letterhead">
            <div class="dl-republic">Republic of the Philippines</div>
            <div class="dl-uni">Northern Eastern Mindanao State University</div>
            <div class="dl-campus">{{ previewData.employee.campus ?? 'NEMSU' }} · Human Resources Management Office</div>
            <div class="dl-badge">CSC Form 48</div>
          </div>

          <!-- Title bar -->
          <div class="doc-title-bar">
            <h3>
              Daily Time Record
              <span v-if="previewData.period?.key !== 'full'" class="doc-period-badge">
                {{ previewData.period.key === 'first' ? '1st Half' : '2nd Half' }}
              </span>
            </h3>
            <p>Period: {{ previewData.month_label }} &nbsp;·&nbsp; Official Hours of Work</p>
          </div>

          <!-- Employee info -->
          <div class="doc-emp-grid">
            <div class="def-item"><span class="def-label">Employee Name</span><span class="def-val">{{ previewData.employee.name }}</span></div>
            <div class="def-item"><span class="def-label">Employee ID</span><span class="def-val">{{ previewData.employee.employee_id ?? '—' }}</span></div>
            <div class="def-item"><span class="def-label">Position</span><span class="def-val">{{ previewData.employee.position ?? '—' }}</span></div>
            <div class="def-item"><span class="def-label">Department</span><span class="def-val">{{ previewData.employee.department ?? '—' }}</span></div>
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

          <!-- Mark / Unmark On Leave action bar (own record only) -->
          <div v-if="isOwnRecord && selectedLeaveDates.size > 0" class="leave-action-bar">
            <span class="text-xs text-[#475569]">{{ selectedLeaveDates.size }} day{{ selectedLeaveDates.size !== 1 ? 's' : '' }} selected</span>
            <div class="flex items-center gap-2">
              <button
                v-if="selectedToMark.length"
                @click="submitMarkOnLeave"
                :disabled="markingLeave"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors disabled:opacity-60"
                style="background:#EEF2FF;color:#3730A3;border:1px solid #C7D2FE;"
              >
                <Loader2Icon v-if="markingLeave" class="w-3.5 h-3.5 animate-spin" />
                Mark {{ selectedToMark.length }} as On Leave
              </button>
              <button
                v-if="selectedToUnmark.length"
                @click="submitUnmarkOnLeave"
                :disabled="unmarkingLeave"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors disabled:opacity-60"
                style="background:#F1F5F9;color:#334155;border:1px solid #E2E8F0;"
              >
                <Loader2Icon v-if="unmarkingLeave" class="w-3.5 h-3.5 animate-spin" />
                Remove On Leave ({{ selectedToUnmark.length }})
              </button>
              <button @click="selectedLeaveDates = new Set()" class="text-xs text-[#6B7A94] hover:text-[#0F172A]">Clear</button>
            </div>
          </div>

          <!-- DTR table -->
          <div class="report-table-wrap overflow-x-auto">
            <table class="dtr-table">
              <thead>
                <tr>
                  <th v-if="isOwnRecord" class="dtr-th" style="width:30px;"></th>
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
                  <td v-if="isOwnRecord" class="dtr-td">
                    <input
                      v-if="row.leaveEligible"
                      type="checkbox"
                      :checked="selectedLeaveDates.has(row.isoDate)"
                      @change="toggleLeaveDate(row)"
                      :title="row.status === 'on_leave' ? 'Select to remove On Leave' : 'Select to mark as On Leave'"
                    />
                  </td>
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
                    <StatusBadge v-if="row.status && row.status !== 'weekend'" :status="row.status" />
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

          <!-- Certification -->
          <div class="doc-certif">
            <strong>I CERTIFY</strong> on my honor that the above is a true and correct report of the hours of work
            performed, record of which was made daily at the time of arrival at and departure from office.
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
            <div v-else-if="canDownloadOwn" class="af-actions">
              <button @click="download" :disabled="downloading" class="btn-primary inline-flex items-center gap-2">
                <Loader2Icon v-if="downloading" class="w-4 h-4 animate-spin" />
                <DownloadIcon v-else class="w-4 h-4" />
                Download CSC Form 48
              </button>
            </div>
            <span v-else class="af-note">
              <LockIcon class="w-3.5 h-3.5" />
              Preview only — request print permission above to download an official copy.
            </span>
          </div>

        </div>
        </template>

        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/composables/useApiError';
import StatusBadge from '@/components/StatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import DtrPeriodPicker from '@/components/DtrPeriodPicker.vue';
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
  Send          as SendIcon,
  XCircle       as XCircleIcon,
  ShieldCheck   as ShieldCheckIcon,
  PenLine       as PenLineIcon,
  Upload        as UploadIcon,
  History       as HistoryIcon,
  ChevronDown   as ChevronDownIcon,
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

// "Mark as On Leave" is self-service only — it's available whenever the DTR
// being viewed is the signed-in user's own, regardless of role (an HR
// Officer looking at their own record via the picker gets it too; looking
// at anyone else's does not).
const isOwnRecord = computed(() =>
  !!effectiveEmployee.value && !!auth.employee && effectiveEmployee.value.id === auth.employee.id
);

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

// ── DTR coverage — COS / Job Order staff print one DTR per half month ──
const period          = ref('full'); // 'full' | 'first' | 'second'
// Kept current by AppLayout's refreshAccess() on load, so a changed
// employment status shows up without signing out.
const selfSemiMonthly = computed(() => !!auth.employee?.semi_monthly_dtr);

const isSemiMonthly = computed(() =>
  canSelectEmployee.value ? !!selected.value?.semi_monthly_dtr : selfSemiMonthly.value
);

const lastDayOfMonth = computed(() => new Date(year.value, month.value, 0).getDate());

const EMPLOYMENT_LABELS = { cos: 'Contractual (COS)', job_order: 'Job Order' };
const employmentLabel = (status) => EMPLOYMENT_LABELS[status] ?? 'Semi-monthly DTR';

// Semi-monthly staff start on the half they're most likely printing — the
// current half when looking at this month, otherwise the 1st half.
function defaultPeriod() {
  if (!isSemiMonthly.value) return 'full';
  const isCurrentMonth = month.value === now.getMonth() + 1 && year.value === now.getFullYear();
  return isCurrentMonth && now.getDate() > 15 ? 'second' : 'first';
}
period.value = defaultPeriod();
watch(isSemiMonthly, () => { period.value = defaultPeriod(); });

const periodParams = computed(() => ({ month: month.value, year: year.value, period: period.value }));

const periodFileSuffix = computed(() => ({
  first:  '_1-15',
  second: `_16-${lastDayOfMonth.value}`,
}[period.value] ?? ''));

const bulkMonth      = ref(now.getMonth() + 1);
const bulkYear       = ref(now.getFullYear());
const bulkDepartment = ref('');
const bulkStaffType  = ref('');
const departments    = ref([]);

// ── DTR Print Consent (employees need HR/Admin sign-off to download) ──
const myConsents       = ref([]);   // employee's own requests, newest first
const consentQueue     = ref([]);   // HR/Admin: campus-scoped requests (all statuses)
const showConsentHistory = ref(false);
const consentPurpose   = ref('');
const requestingConsent = ref(false);
const reviewingId = ref(null);
const denyingId   = ref(null);
const denyRemarks = ref('');
const justArrivedIds = ref(new Set()); // brief highlight for rows that just landed live

const selectedPeriodLabel = computed(() => {
  const m = months.find(x => x.value === month.value)?.label ?? '';
  if (period.value === 'first')  return `${m} 1–15, ${year.value}`;
  if (period.value === 'second') return `${m} 16–${lastDayOfMonth.value}, ${year.value}`;
  return `${m} ${year.value}`;
});

const currentPeriodConsent = computed(() =>
  myConsents.value.find(c =>
    c.month === month.value && c.year === year.value && (c.period ?? 'full') === period.value
  ) ?? null
);

const canDownloadOwn = computed(() =>
  !canSelectEmployee.value &&
  currentPeriodConsent.value?.status === 'approved' &&
  !currentPeriodConsent.value?.used_at
);

const pendingConsents = computed(() => consentQueue.value.filter(c => c.status === 'pending'));
const recentConsentDecisions = computed(() =>
  consentQueue.value.filter(c => c.status !== 'pending').slice(0, 5)
);

// ── Tabs ──────────────────────────────────────────────────────────
const activeTab = ref('generate');
const reportTabs = computed(() => {
  const tabs = [
    { key: 'generate', label: 'Generate & Preview', icon: FileTextIcon },
    {
      key: 'requests',
      label: canSelectEmployee.value ? 'Print Requests' : 'Print Permission',
      icon: ShieldCheckIcon,
      badge: canSelectEmployee.value ? pendingConsents.value.length : 0,
      dot: !canSelectEmployee.value && canDownloadOwn.value,
    },
  ];
  if (canSelectEmployee.value) {
    tabs.push({ key: 'bulk', label: 'Bulk Export', icon: FileDownIcon });
  }
  return tabs;
});

// ── Reviewer's own digital signature ───────────────────────────────
const mySignatureUrl     = ref(null);
const mySignatureEnabled = ref(false);
const signatureFile      = ref(null);
const uploadingSignature = ref(false);
const togglingSignature  = ref(false);


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
// Spans exactly the previewed period (whole month or one half), taken from
// the response itself so the rows always match the data they were built from.
const fullMonthRows = computed(() => {
  if (!previewData.value) return [];
  const { year: y, month: m, start_day: firstDay, end_day: lastDay } = previewData.value.period;
  const recordMap = {};
  for (const r of previewData.value.records) recordMap[r.day] = r;

  const rows = [];
  for (let d = firstDay; d <= lastDay; d++) {
    const date    = new Date(y, m - 1, d);
    const dow     = date.getDay();
    const isWeekend = dow === 0 || dow === 6;
    const dayName = date.toLocaleDateString('en-PH', { weekday: 'short' });
    const rec     = recordMap[d];
    const status  = rec?.status ?? (isWeekend ? 'weekend' : null);

    rows.push({
      day:               d,
      isoDate:           `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`,
      dayName,
      isWeekend,
      status,
      morning_in:        rec?.morning_in   ?? null,
      morning_out:       rec?.morning_out  ?? null,
      afternoon_in:      rec?.afternoon_in ?? null,
      afternoon_out:     rec?.afternoon_out ?? null,
      hours:             Number(rec?.hours) || 0,
      tardiness_minutes: rec?.tardiness_minutes ?? 0,
      overtime_minutes:  rec?.overtime_minutes  ?? 0,
      // Eligible whenever there's no evidence of punches: either the day
      // was already computed as absent, it's already on_leave (so it can
      // be unmarked), or — the most common real case — nothing has ever
      // touched this date at all (status is still null) because nothing
      // automatically marks a never-punched day absent at end-of-day.
      leaveEligible:     !isWeekend && (status === null || status === 'absent' || status === 'on_leave'),
    });
  }
  return rows;
});

const workdayCount = computed(() => fullMonthRows.value.filter(r => !r.isWeekend).length);

// ── Mark / Unmark On Leave (self-service, own record only) ──────────────
const selectedLeaveDates = ref(new Set());
const markingLeave       = ref(false);
const unmarkingLeave     = ref(false);

const selectedToMark   = computed(() => fullMonthRows.value.filter(r => selectedLeaveDates.value.has(r.isoDate) && r.status !== 'on_leave'));
const selectedToUnmark = computed(() => fullMonthRows.value.filter(r => selectedLeaveDates.value.has(r.isoDate) && r.status === 'on_leave'));

function toggleLeaveDate(row) {
  if (!row.leaveEligible) return;
  const next = new Set(selectedLeaveDates.value);
  if (next.has(row.isoDate)) next.delete(row.isoDate);
  else next.add(row.isoDate);
  selectedLeaveDates.value = next;
}

async function submitMarkOnLeave() {
  if (!selectedToMark.value.length) return;
  markingLeave.value = true;
  try {
    const dates = selectedToMark.value.map(r => r.isoDate);
    const res = await axios.post('/api/attendance/on-leave', { dates });
    toast(`Marked ${res.data.marked} day${res.data.marked !== 1 ? 's' : ''} as On Leave.`, 'success');
    selectedLeaveDates.value = new Set();
    await loadPreview();
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to mark day(s) as On Leave.'), 'error');
  } finally {
    markingLeave.value = false;
  }
}

async function submitUnmarkOnLeave() {
  if (!selectedToUnmark.value.length) return;
  unmarkingLeave.value = true;
  try {
    const dates = selectedToUnmark.value.map(r => r.isoDate);
    const res = await axios.post('/api/attendance/on-leave/unmark', { dates });
    toast(`Removed On Leave from ${res.data.unmarked} day${res.data.unmarked !== 1 ? 's' : ''}.`, 'success');
    selectedLeaveDates.value = new Set();
    await loadPreview();
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to remove On Leave.'), 'error');
  } finally {
    unmarkingLeave.value = false;
  }
}

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

// A preview only describes the period it was loaded for — drop it when the
// selection changes so "Download PDF" never acts on a different span than
// the one on screen.
watch([month, year, period], () => {
  previewData.value = null;
  selectedLeaveDates.value = new Set();
});

// ── API actions ───────────────────────────────────────────────────
async function loadPreview() {
  if (!effectiveEmployee.value) return;
  previewing.value  = true;
  previewData.value = null;
  selectedLeaveDates.value = new Set();
  try {
    const res = await axios.get(`/api/reports/preview/${effectiveEmployee.value.id}`, {
      params: periodParams.value,
    });
    previewData.value = res.data;
  } catch (err) {
    toast(err.response?.data?.message ?? 'Failed to load preview.', 'error');
  } finally {
    previewing.value = false;
  }
}

async function download() {
  if (!effectiveEmployee.value || !(canSelectEmployee.value || canDownloadOwn.value)) return;
  downloading.value = true;
  try {
    const res = await axios.get(`/api/reports/form48/${effectiveEmployee.value.id}`, {
      params: periodParams.value,
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
    link.setAttribute('download', `DTR_${empId ?? 'employee'}_${year.value}_${String(month.value).padStart(2,'0')}${periodFileSuffix.value}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    if (!canSelectEmployee.value) await fetchMyConsents();
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
      params: periodParams.value,
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
    link.setAttribute('download', `DTR_${empId ?? 'employee'}_${year.value}_${String(month.value).padStart(2,'0')}${periodFileSuffix.value}.csv`);
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

// ── DTR Print Consent ───────────────────────────────────────────────
async function fetchMyConsents() {
  try {
    const res = await axios.get('/api/dtr-print-consents');
    myConsents.value = res.data?.consents ?? [];
  } catch { /* ignore */ }
}

async function fetchConsentQueue() {
  try {
    const res = await axios.get('/api/dtr-print-consents');
    consentQueue.value = res.data?.consents ?? [];
  } catch { /* ignore */ }
}

async function requestConsent() {
  requestingConsent.value = true;
  try {
    await axios.post('/api/dtr-print-consents', {
      ...periodParams.value,
      purpose: consentPurpose.value || null,
    });
    toast('Print request submitted. HR/Admin will review it shortly.', 'success');
    consentPurpose.value = '';
    await fetchMyConsents();
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to submit print request.'), 'error');
  } finally {
    requestingConsent.value = false;
  }
}

// Patches the reviewed row in place instead of refetching the whole queue —
// review() already returns the fully-updated consent, and re-fetching
// /api/dtr-print-consents means re-running a query over the campus's whole
// request history (which only grows — every approval is single-use, so a
// busy campus racks up months of these) just to redraw one changed row.
function applyConsentUpdate(updated) {
  if (!updated) return;
  const idx = consentQueue.value.findIndex(c => c.id === updated.id);
  if (idx !== -1) consentQueue.value.splice(idx, 1);
  // Moved to the front rather than updated in place — a request that just
  // got reviewed should read as the most recent decision, not stay stuck
  // wherever it happened to sit in the list from the original page load.
  consentQueue.value.unshift(updated);
}

function flashAsNew(id) {
  justArrivedIds.value.add(id);
  setTimeout(() => justArrivedIds.value.delete(id), 2500);
}

// Patches myConsents the same way applyConsentUpdate patches the HR/Admin
// queue — so an employee sitting on this page sees a decision land without
// reloading.
function applyMyConsentUpdate(updated) {
  if (!updated) return;
  const idx = myConsents.value.findIndex(c => c.id === updated.id);
  if (idx !== -1) myConsents.value.splice(idx, 1);
  myConsents.value.unshift(updated);
}

// ── Live updates for the print-request queue (Reverb) ────────────────
// HR/Admin: a new request appears the instant it's submitted, and any
// request reviewed in another tab/by another reviewer drops out of
// "pending" immediately, without a page reload.
// Employee: their own "Print Permission" card reflects a decision live.
let printConsentChannel = null;

function onPrintRequestedLive(e) {
  if (!canSelectEmployee.value || !e?.consent) return;
  // A public channel carries every campus's traffic — only react to the
  // ones this viewer is actually scoped to see.
  if (!isSuperAdmin.value && e.campus_code && e.campus_code !== auth.employee?.campus?.code) return;
  if (consentQueue.value.some(c => c.id === e.consent.id)) return;
  consentQueue.value.unshift(e.consent);
  flashAsNew(e.consent.id);
  toast(`New print request from ${e.consent.employee?.name ?? 'an employee'}.`, 'info', 6000);
}

function onPrintDecidedLive(e) {
  if (!e?.consent) return;
  if (canSelectEmployee.value) {
    if (!isSuperAdmin.value && e.campus_code && e.campus_code !== auth.employee?.campus?.code) return;
    applyConsentUpdate(e.consent);
    flashAsNew(e.consent.id);
  } else if (e.employee_id === auth.employee?.id) {
    applyMyConsentUpdate(e.consent);
  }
}

function listenForPrintConsents() {
  if (!window.Echo) return;
  printConsentChannel = window.Echo.channel('print-consents')
    .listen('.requested', onPrintRequestedLive)
    .listen('.decided', onPrintDecidedLive);
}

async function approveConsent(c) {
  reviewingId.value = c.id;
  try {
    const res = await axios.patch(`/api/dtr-print-consents/${c.id}`, { status: 'approved' });
    toast(`Approved ${c.employee.name}'s print request.`, 'success');
    applyConsentUpdate(res.data?.consent);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to approve request.'), 'error');
  } finally {
    reviewingId.value = null;
  }
}

async function denyConsent(c) {
  reviewingId.value = c.id;
  try {
    const res = await axios.patch(`/api/dtr-print-consents/${c.id}`, {
      status: 'denied',
      reviewer_remarks: denyRemarks.value || null,
    });
    toast(`Denied ${c.employee.name}'s print request.`, 'success');
    denyingId.value = null;
    applyConsentUpdate(res.data?.consent);
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to deny request.'), 'error');
  } finally {
    reviewingId.value = null;
  }
}

// ── Reviewer's own digital signature ───────────────────────────────
async function fetchMySignature() {
  try {
    const res = await axios.get('/api/my-signature');
    mySignatureUrl.value     = res.data?.signature_url ?? null;
    mySignatureEnabled.value = res.data?.signature_enabled ?? false;
  } catch { /* ignore */ }
}

function onSignatureSelected(e) {
  const file = e.target.files?.[0];
  if (file) uploadSignature(file);
}

async function uploadSignature(file) {
  uploadingSignature.value = true;
  try {
    const form = new FormData();
    form.append('signature', file);
    const res = await axios.post('/api/my-signature', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    mySignatureUrl.value     = res.data?.signature_url ?? null;
    mySignatureEnabled.value = res.data?.signature_enabled ?? true;
    toast(res.data?.message ?? 'Signature uploaded.', 'success');
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to upload signature.'), 'error');
  } finally {
    uploadingSignature.value = false;
    signatureFile.value = null;
  }
}

async function toggleSignature(enabled) {
  togglingSignature.value = true;
  try {
    const res = await axios.patch('/api/my-signature', { enabled });
    mySignatureEnabled.value = res.data?.signature_enabled ?? enabled;
    toast(res.data?.message ?? 'Preference updated.', 'success');
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to update preference.'), 'error');
  } finally {
    togglingSignature.value = false;
  }
}

async function removeSignature() {
  uploadingSignature.value = true;
  try {
    await axios.delete('/api/my-signature');
    mySignatureUrl.value     = null;
    mySignatureEnabled.value = false;
    toast('Signature removed.', 'success');
  } catch (err) {
    toast(await extractErrorMessage(err, 'Failed to remove signature.'), 'error');
  } finally {
    uploadingSignature.value = false;
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
    await Promise.all([fetchConsentQueue(), fetchMySignature()]);
  } else {
    await fetchMyConsents();
  }
  listenForPrintConsents();
});

onUnmounted(() => {
  printConsentChannel?.stopListening('.requested', onPrintRequestedLive);
  printConsentChannel?.stopListening('.decided', onPrintDecidedLive);
});
</script>

<style scoped>
@reference "../css/app.css";

.form-label { @apply block text-sm font-medium text-[#0F172A] mb-1.5; }
.form-input  { @apply w-full px-3 py-2 text-sm border border-[#E2E8F0] rounded-md text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#2E4A8C] bg-white; }
.btn-primary { @apply px-4 py-2 bg-[#2E4A8C] text-white text-sm font-medium rounded-lg hover:bg-[#3D5CA3] disabled:opacity-60 transition-colors; }
.btn-outline  { @apply px-4 py-2 text-sm font-medium border border-[#E2E8F0] rounded-lg text-[#374151] hover:bg-[#F0F4FF] transition-colors disabled:opacity-60; }
.bulk-label  { @apply block text-xs font-semibold text-[#374151] mb-1.5 uppercase tracking-wide; }

/* ── Card header — shared across every card on this page ─────────
   A tinted strip with a colored left-border accent instead of a
   solid navy/green gradient fill — same visual language as the
   dashboard's alert banners, just less heavy for something that
   appears at the top of every card on the page. Color is set per
   instance via --accent/--tint so one class covers every variant. */
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 16px 24px;
  border-bottom: 1px solid #E2E8F0;
  border-left: 4px solid var(--accent, #1B2A5E);
  background: var(--tint, #F4F6FB);
}
.card-head-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.card-head-icon {
  width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: color-mix(in srgb, var(--accent, #1B2A5E) 14%, transparent);
  color: var(--accent, #1B2A5E);
}
.card-head-title { font-size: 14px; font-weight: 700; color: #0F172A; }
.card-head-sub   { font-size: 12px; color: #64748B; margin-top: 2px; }

/* ── Tab bar ───────────────────────────────────────────────────── */
.tab-bar {
  display: flex;
  align-items: center;
  gap: 2px;
  border-bottom: 1px solid #E2E8F0;
  margin-bottom: 20px;
  overflow-x: auto;
}
.tab-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 16px;
  font-size: 13.5px;
  font-weight: 600;
  color: #6B7A94;
  white-space: nowrap;
  transition: color 0.15s;
  flex-shrink: 0;
}
.tab-btn:hover { color: #1B2A5E; }
.tab-btn--active { color: #1B2A5E; }
.tab-btn--active::after {
  content: '';
  position: absolute;
  left: 2px; right: 2px; bottom: -1px;
  height: 2px;
  background: #1B2A5E;
  border-radius: 2px 2px 0 0;
}
.tab-badge {
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  background: #EF4444;
  border-radius: 999px;
  padding: 1px 6px;
  line-height: 1.4;
}
.tab-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #15803D;
  flex-shrink: 0;
  animation: tab-dot-pulse 2s ease infinite;
}
@keyframes tab-dot-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(21,128,61,0.4); }
  50%       { box-shadow: 0 0 0 4px rgba(21,128,61,0); }
}

@media (max-width: 640px) {
  .tab-btn { padding: 9px 12px; font-size: 12.5px; }
}

/* ── Print-requests empty state ──────────────────────────────────── */
.empty-pending {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 10px;
  background: #F0FDF4;
  border: 1px dashed #BBF7D0;
  color: #166534;
  font-size: 13px;
  font-weight: 500;
}
.empty-pending svg { color: #15803D; flex-shrink: 0; }

/* ── Pending request row ─────────────────────────────────────────── */
.pending-row {
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.pending-row:hover {
  border-color: #C7D2F6;
  box-shadow: 0 2px 10px -4px rgba(27,42,94,0.15);
  transform: translateY(-1px);
}

/* ── Live indicator (card header) ────────────────────────────────── */
.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #15803D;
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
  border-radius: 999px;
  padding: 2px 8px 2px 6px;
}
.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #22C55E;
  animation: live-pulse 1.8s ease-in-out infinite;
}
@keyframes live-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
  50% { box-shadow: 0 0 0 4px rgba(34,197,94,0); }
}

/* ── Rows that just arrived via live update ──────────────────────── */
.pending-row--new,
.history-row--new {
  animation: row-flash-in 2.2s ease-out;
}
@keyframes row-flash-in {
  0% { background: #EFF6FF; border-color: #93C5FD; }
  100% { background: transparent; border-color: #E2E8F0; }
}

/* ── Request History (collapsible) ───────────────────────────────── */
.history-section { border-top: 1px solid #F1F5F9; padding-top: 10px; margin-top: 2px; }
.history-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 2px;
  font-size: 12.5px;
  font-weight: 600;
  color: #2E4A8C;
  transition: color 0.15s ease;
}
.history-toggle:hover { color: #1B2A5E; }
.history-toggle-left { display: flex; align-items: center; gap: 7px; }
.history-count {
  font-size: 10.5px;
  font-weight: 700;
  color: #475569;
  background: #E2E8F0;
  border-radius: 999px;
  padding: 1px 7px;
  line-height: 1.5;
}
.chevron { color: #94A3B8; transition: transform 0.2s ease; flex-shrink: 0; }
.chevron--open { transform: rotate(180deg); }

.history-collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.25s ease;
}
.history-collapse.is-open { grid-template-rows: 1fr; }
.history-collapse-inner { overflow: hidden; min-height: 0; }
.history-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 8px;
}
.history-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #FAFBFD;
  border: 1px solid #F1F5F9;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.history-row:hover { background: #F4F7FC; border-color: #E2E8F0; }

/* ── Document-style letterhead — mirrors the printed CSC Form 48 ─── */
.doc-preview { position: relative; }
.ph-close {
  position: absolute; top: 14px; right: 14px; z-index: 2;
  color: #94A3B8; padding: 5px; border-radius: 6px; transition: all 0.15s; background: #fff;
}
.ph-close:hover { color: #0F172A; background: #F1F5F9; }

.doc-letterhead {
  text-align: center;
  padding: 18px 44px 14px;
  border-bottom: 2px solid #24356F;
  background: #FAFBFD;
  position: relative;
}
.dl-republic { font-size: 11px; color: #64748B; letter-spacing: 0.03em; }
.dl-uni { font-size: 17px; font-weight: 800; color: #1B2A5E; letter-spacing: 0.01em; margin-top: 2px; }
.dl-campus { font-size: 11px; color: #64748B; margin-top: 3px; }
.dl-badge {
  position: absolute; top: 16px; right: 44px;
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
  color: #C9A227; border: 1px solid #C9A227; border-radius: 5px; padding: 3px 8px;
}

.doc-title-bar {
  text-align: center;
  padding: 12px 20px 10px;
  border-bottom: 1px solid #F4F6FB;
}
.doc-title-bar h3 { font-size: 16px; font-weight: 700; color: #0F172A; letter-spacing: 0.02em; }
.doc-title-bar p  { font-size: 11.5px; color: #6B7A94; margin-top: 2px; }
.doc-period-badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.03em;
  vertical-align: middle;
  color: #2E4A8C;
  background: #EEF2FF;
}

/* Employment pill next to a semi-monthly employee's name */
.emp-status-pill {
  display: inline-flex;
  align-items: center;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  color: #B45309;
  background: #FEF3C7;
}
[data-theme="dark"] .doc-title-bar h3   { color: #E4EAF5; }
[data-theme="dark"] .doc-period-badge { color: #93C5FD; background: rgba(96, 165, 250, 0.14); }
[data-theme="dark"] .emp-status-pill  { color: #FBBF24; background: rgba(251, 191, 36, 0.12); }

/* The coverage picker slides in when a semi-monthly employee is selected */
.coverage-enter-active, .coverage-leave-active { transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.22, 1, 0.36, 1); }
.coverage-enter-from, .coverage-leave-to { opacity: 0; transform: translateY(-4px); }

.doc-emp-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 28px;
  padding: 14px 20px;
  border-bottom: 1px solid #F4F6FB;
  background: #F8FAFC;
}
.def-item { display: flex; align-items: baseline; gap: 8px; min-width: 0; }
.def-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6B7A94; width: 100px; flex-shrink: 0; }
.def-val   { font-size: 13px; font-weight: 600; color: #0F172A; border-bottom: 1px solid #DDE4EE; flex: 1; padding-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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

/* ── Mark/Unmark On Leave action bar ──────────────────────────────── */
.leave-action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px;
  background: #EEF2FF;
  border-bottom: 1px solid #C7D2FE;
  flex-wrap: wrap;
}

/* ── DTR table ───────────────────────────────────── */
.dtr-table { width: 100%; border-collapse: collapse; font-size: 12px; }

.dtr-th {
  padding: 8px 10px;
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #fff;
  background: #24356F;
  border: 1px solid #0A1550;
  text-align: center;
  white-space: nowrap;
}
.dtr-th-day { text-align: left; width: 72px; padding-left: 14px; }

.dtr-row { transition: background 0.1s; }
.dtr-row:hover { filter: brightness(0.97); }

.dtr-row--weekend { background: #F8FAFC; }
.dtr-row--present { background: #fff; }
.dtr-row--late    { background: #FFFBEB; }
.dtr-row--absent  { background: #FEF2F2; }
.dtr-row--holiday { background: #F0F9FF; }
.dtr-row--leave   { background: #EEF2FF; }

.dtr-td { padding: 7px 10px; text-align: center; vertical-align: middle; border: 0.5px solid #E2E8F0; }
.dtr-td-day  { display: flex; align-items: center; gap: 6px; text-align: left; padding-left: 14px; }
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

/* ── Certification ────────────────────────────────── */
.doc-certif {
  font-size: 12px;
  line-height: 1.6;
  font-style: italic;
  color: #374151;
  text-align: justify;
  padding: 12px 20px;
  background: #FAFAFA;
  border-top: 1px solid #F4F6FB;
  border-left: 3px solid #C9A227;
}

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

  /* Letterhead: give the CSC badge room instead of overlapping the title */
  .doc-letterhead { padding: 16px 56px 12px; }
  .dl-badge { position: static; display: inline-block; margin-top: 8px; }
  .dl-uni { font-size: 15px; }
  .doc-emp-grid { grid-template-columns: 1fr; }
  .def-label { width: 88px; }

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
