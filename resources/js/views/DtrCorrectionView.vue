<template>
  <div class="flex flex-col h-full" style="background:#F4F6FB;">

    <PageHeader
      title="DTR Correction Requests"
      subtitle="Submit and track requests to correct missing or incorrect time entries"
      :icon="ClipboardEditIcon"
    >
      <button
        @click="openSubmit"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-colors ph-btn-primary"
      >
        <PlusIcon class="w-4 h-4" />
        New Request
      </button>
    </PageHeader>

    <div class="flex-1 overflow-y-auto">
      <div class="max-w-screen-xl mx-auto px-6 py-6 space-y-5">

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white rounded-xl px-5 py-4 cursor-pointer transition-shadow hover:shadow-md"
            :style="activeTab === 'mine' && statusFilter === 'pending' ? 'border:1.5px solid #F59E0B;' : 'border:1px solid #E5E7EB;'"
            @click="setFilter('pending')"
          >
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FFFBEB;">
                <ClockIcon class="w-4 h-4" style="color:#D97706;" />
              </div>
              <div>
                <p class="text-xs font-medium" style="color:#9CA3AF;">Pending</p>
                <p class="text-2xl font-bold" style="color:#0F172A;">{{ summary.pending ?? 0 }}</p>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl px-5 py-4 cursor-pointer transition-shadow hover:shadow-md"
            :style="activeTab === 'mine' && statusFilter === 'approved' ? 'border:1.5px solid #10B981;' : 'border:1px solid #E5E7EB;'"
            @click="setFilter('approved')"
          >
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#ECFDF5;">
                <CheckCircleIcon class="w-4 h-4" style="color:#10B981;" />
              </div>
              <div>
                <p class="text-xs font-medium" style="color:#9CA3AF;">Approved</p>
                <p class="text-2xl font-bold" style="color:#0F172A;">{{ summary.approved ?? 0 }}</p>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl px-5 py-4 cursor-pointer transition-shadow hover:shadow-md"
            :style="activeTab === 'mine' && statusFilter === 'denied' ? 'border:1.5px solid #EF4444;' : 'border:1px solid #E5E7EB;'"
            @click="setFilter('denied')"
          >
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FEF2F2;">
                <XCircleIcon class="w-4 h-4" style="color:#EF4444;" />
              </div>
              <div>
                <p class="text-xs font-medium" style="color:#9CA3AF;">Denied</p>
                <p class="text-2xl font-bold" style="color:#0F172A;">{{ summary.denied ?? 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Info banner for employees -->
        <div v-if="!isPrivileged" class="flex items-start gap-3 px-4 py-3 rounded-xl" style="background:#EFF6FF;border:1px solid #BFDBFE;">
          <InfoIcon class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#3B82F6;" />
          <p class="text-sm" style="color:#1D4ED8;">
            Use this page to request corrections for missed or incorrect time punches. You can correct multiple fields in a single request. Your HR Officer or Admin will review and approve within 1–2 business days.
          </p>
        </div>

        <!-- Tabs (privileged users only) -->
        <div v-if="isPrivileged" class="flex items-center gap-1 p-1 rounded-xl w-fit" style="background:#E2E8F0;">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="switchTab(tab.key)"
            class="px-4 py-1.5 text-sm font-medium rounded-lg transition-all"
            :style="activeTab === tab.key
              ? 'background:white;color:#0F172A;box-shadow:0 1px 3px rgba(0,0,0,0.1);'
              : 'color:#64748B;'"
          >
            {{ tab.label }}
            <span
              v-if="tab.key === 'campus' && summary.pending > 0"
              class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full text-[10px] font-bold text-white"
              style="background:#EF4444;"
            >{{ summary.pending }}</span>
          </button>
        </div>

        <!-- Table card -->
        <div class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">

          <!-- Batch action bar (campus tab, when items selected) -->
          <div
            v-if="isPrivileged && activeTab === 'campus' && selectedIds.size > 0"
            class="px-5 py-3 flex items-center gap-3"
            style="background:#EFF6FF;border-bottom:1px solid #BFDBFE;"
          >
            <LinkIcon class="w-4 h-4 flex-shrink-0" style="color:#3B82F6;" />
            <span class="text-sm font-semibold" style="color:#1D4ED8;">
              {{ selectedIds.size }} request{{ selectedIds.size !== 1 ? 's' : '' }} selected
            </span>
            <div class="flex items-center gap-2 ml-auto">
              <button
                @click="openBatchReview('approved')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                style="background:#15803D;color:white;"
              >
                <CheckIcon class="w-3.5 h-3.5" />
                Approve Selected
              </button>
              <button
                @click="openBatchReview('denied')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                style="background:#B91C1C;color:white;"
              >
                <XIcon class="w-3.5 h-3.5" />
                Deny Selected
              </button>
              <button
                @click="selectedIds.clear(); selectedIds = new Set()"
                class="text-xs font-medium px-2 py-1.5 rounded-lg transition-colors"
                style="color:#64748B;"
              >
                Clear
              </button>
            </div>
          </div>

          <!-- Table header -->
          <div class="px-5 py-3.5 flex items-center justify-between" style="border-bottom:1px solid #F1F5F9;">
            <h2 class="text-sm font-semibold" style="color:#0F172A;">
              {{ activeTab === 'campus' ? 'Campus Correction Requests' : 'My Correction Requests' }}
            </h2>
            <div class="flex items-center gap-2">
              <select
                v-model="statusFilter"
                @change="load"
                class="text-xs border rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1"
                style="border-color:#E5E7EB;color:#374151;background:white;"
              >
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="denied">Denied</option>
              </select>
              <button @click="load" :disabled="loading" class="text-xs font-medium flex items-center gap-1 transition-colors" style="color:#64748B;">
                <RefreshCwIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
                Refresh
              </button>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="p-10 text-center">
            <Loader2Icon class="w-7 h-7 mx-auto animate-spin mb-3" style="color:#C4B5FD;" />
            <p class="text-sm" style="color:#9CA3AF;">Loading requests…</p>
          </div>

          <!-- Empty -->
          <div v-else-if="corrections.length === 0" class="p-14 text-center">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#F8FAFC;">
              <ClipboardEditIcon class="w-7 h-7 opacity-20" style="color:#64748B;" />
            </div>
            <p class="text-sm font-semibold mb-1" style="color:#374151;">No correction requests</p>
            <p class="text-xs" style="color:#9CA3AF;">
              {{ statusFilter ? `No ${statusFilter} requests found.` : 'No requests yet. Click "New Request" to submit one.' }}
            </p>
          </div>

          <!-- Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr style="background:#FAFAFA;border-bottom:1px solid #F1F5F9;">
                  <!-- Batch checkbox (campus tab, privileged) -->
                  <th v-if="isPrivileged && activeTab === 'campus'" class="pl-4 pr-2 py-3 w-8">
                    <input
                      type="checkbox"
                      class="rounded"
                      :checked="allPendingSelected"
                      :indeterminate="somePendingSelected && !allPendingSelected"
                      @change="toggleSelectAll"
                      title="Select all pending"
                    />
                  </th>
                  <th v-if="activeTab === 'campus'" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Employee</th>
                  <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Date</th>
                  <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Field</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Change</th>
                  <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Reason</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Status</th>
                  <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider" style="color:#9CA3AF;">Submitted</th>
                  <th v-if="isPrivileged && activeTab === 'campus'" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="c in corrections"
                  :key="c.id"
                  class="transition-colors hover:bg-[#FAFAFA]"
                  :style="[
                    'border-bottom:1px solid #F9FAFB;',
                    selectedIds.has(c.id) ? 'background:#EFF6FF;' : ''
                  ].join('')"
                >
                  <!-- Batch checkbox -->
                  <td v-if="isPrivileged && activeTab === 'campus'" class="pl-4 pr-2 py-3.5">
                    <input
                      v-if="c.status === 'pending'"
                      type="checkbox"
                      class="rounded"
                      :checked="selectedIds.has(c.id)"
                      @change="toggleSelect(c.id)"
                    />
                  </td>

                  <!-- Employee (campus tab only) -->
                  <td v-if="activeTab === 'campus'" class="px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                      <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold"
                        style="background:linear-gradient(135deg,#0E1EC0,#1228CC);">
                        {{ initials(c.employee?.name) }}
                      </div>
                      <div>
                        <p class="text-xs font-semibold" style="color:#0F172A;">{{ c.employee?.name }}</p>
                        <p class="text-[10px]" style="color:#9CA3AF;">{{ c.employee?.employee_id }}</p>
                      </div>
                    </div>
                  </td>

                  <!-- Date -->
                  <td class="px-5 py-3.5">
                    <p class="text-xs font-semibold font-mono" style="color:#0F172A;">{{ c.work_date }}</p>
                    <p class="text-[10px]" style="color:#9CA3AF;">{{ c.work_date_label }}</p>
                  </td>

                  <!-- Field + group indicator -->
                  <td class="px-4 py-3.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium" style="background:#EEF2FF;color:#4338CA;">
                        {{ c.field_label }}
                      </span>
                      <span
                        v-if="c.group_id"
                        class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md text-[10px] font-medium"
                        style="background:#FDF4FF;color:#7C3AED;"
                        title="Part of a multi-field batch correction"
                      >
                        <LinkIcon class="w-2.5 h-2.5" />
                        Batch
                      </span>
                    </div>
                  </td>

                  <!-- Change: original → requested -->
                  <td class="px-4 py-3.5 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                      <span class="font-mono text-xs px-1.5 py-0.5 rounded" style="background:#FEF2F2;color:#991B1B;">
                        {{ c.original_value ?? 'none' }}
                      </span>
                      <ArrowRightIcon class="w-3 h-3 flex-shrink-0" style="color:#9CA3AF;" />
                      <span class="font-mono text-xs px-1.5 py-0.5 rounded" style="background:#ECFDF5;color:#065F46;">
                        {{ c.requested_value }}
                      </span>
                    </div>
                  </td>

                  <!-- Reason -->
                  <td class="px-4 py-3.5 max-w-[200px]">
                    <p class="text-xs truncate" style="color:#374151;" :title="c.reason">{{ c.reason }}</p>
                    <p v-if="c.reviewer_remarks" class="text-[10px] mt-0.5 italic truncate" style="color:#9CA3AF;" :title="c.reviewer_remarks">
                      Reviewer: {{ c.reviewer_remarks }}
                    </p>
                  </td>

                  <!-- Status badge -->
                  <td class="px-4 py-3.5 text-center">
                    <StatusBadge :status="c.status" />
                    <p v-if="c.reviewed_at" class="text-[10px] mt-1" style="color:#9CA3AF;">{{ c.reviewed_at }}</p>
                  </td>

                  <!-- Submitted date -->
                  <td class="px-4 py-3.5 text-center">
                    <p class="text-xs font-mono" style="color:#64748B;">{{ c.submitted_at }}</p>
                  </td>

                  <!-- Single-item actions (campus tab, privileged) -->
                  <td v-if="isPrivileged && activeTab === 'campus'" class="px-5 py-3.5">
                    <div v-if="c.status === 'pending'" class="flex items-center gap-2">
                      <button
                        @click="openReview(c, 'approved')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg transition-colors"
                        style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;"
                      >
                        <CheckIcon class="w-3 h-3" />
                        Approve
                      </button>
                      <button
                        @click="openReview(c, 'denied')"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg transition-colors"
                        style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;"
                      >
                        <XIcon class="w-3 h-3" />
                        Deny
                      </button>
                    </div>
                    <span v-else class="text-[11px]" style="color:#9CA3AF;">
                      {{ c.reviewed_by_name ?? '—' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Submit Request Modal ───────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="submitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5);" @click.self="submitModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">

          <!-- Header -->
          <div class="px-6 py-4 flex items-center gap-3" style="border-bottom:1px solid #F1F5F9;">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#EEF2FF;">
              <ClipboardEditIcon class="w-4 h-4" style="color:#4338CA;" />
            </div>
            <div class="flex-1">
              <h3 class="text-base font-bold" style="color:#0F172A;">New Correction Request</h3>
              <p class="text-xs" style="color:#64748B;">Select one or more time fields to correct for the same date.</p>
            </div>
            <button @click="submitModal = false" class="p-1.5 rounded-lg transition-colors" style="color:#9CA3AF;">
              <XIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Form -->
          <form @submit.prevent="submitRequest" class="px-6 py-5 space-y-4">

            <!-- Date -->
            <div>
              <label class="modal-label">Date <span style="color:#EF4444;">*</span></label>
              <input
                v-model="form.work_date"
                type="date"
                :max="today"
                required
                class="modal-input"
              />
              <p class="text-[10px] mt-1" style="color:#9CA3AF;">Cannot be a future date.</p>
            </div>

            <!-- Multi-field selector -->
            <div>
              <label class="modal-label">
                Fields to Correct <span style="color:#EF4444;">*</span>
                <span class="float-right font-normal text-xs" style="color:#9CA3AF;">Check all that apply</span>
              </label>
              <div class="space-y-2 mt-1">
                <div
                  v-for="f in FIELD_DEFS"
                  :key="f.key"
                  class="rounded-lg px-3 py-2.5 transition-colors"
                  :style="form.fields[f.key].checked
                    ? 'background:#EEF2FF;border:1px solid #C7D2FE;'
                    : 'background:#F8FAFC;border:1px solid #E5E7EB;'"
                >
                  <label class="flex items-center gap-3 cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="form.fields[f.key].checked"
                      class="rounded accent-indigo-600 w-4 h-4 flex-shrink-0"
                    />
                    <span class="text-sm font-medium flex-1" style="color:#1E293B;">{{ f.label }}</span>
                    <input
                      v-if="form.fields[f.key].checked"
                      v-model="form.fields[f.key].value"
                      type="time"
                      required
                      class="modal-input-sm"
                      @click.stop
                    />
                  </label>
                </div>
              </div>
              <p v-if="noFieldsChecked && submitAttempted" class="text-xs mt-1.5" style="color:#DC2626;">
                Please select at least one field to correct.
              </p>
            </div>

            <!-- Reason -->
            <div>
              <label class="modal-label">
                Reason <span style="color:#EF4444;">*</span>
                <span class="float-right font-normal" style="color:#9CA3AF;">{{ form.reason.length }}/500</span>
              </label>
              <textarea
                v-model="form.reason"
                required
                minlength="10"
                maxlength="500"
                rows="3"
                placeholder="Explain why this correction is needed (e.g., forgot to punch in, biometric error)…"
                class="modal-input resize-none"
              ></textarea>
            </div>

            <div v-if="submitError" class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm" style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;">
              <AlertCircleIcon class="w-4 h-4 flex-shrink-0" />
              {{ submitError }}
            </div>

            <div class="flex gap-3 pt-1">
              <button type="button" @click="submitModal = false" class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors" style="background:#F1F5F9;color:#374151;">
                Cancel
              </button>
              <button type="submit" :disabled="submitting" class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50" style="background:#0E1EC0;color:white;">
                <span v-if="submitting" class="flex items-center justify-center gap-2">
                  <Loader2Icon class="w-4 h-4 animate-spin" /> Submitting…
                </span>
                <span v-else>Submit Request</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Single Review Modal ───────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="reviewTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5);" @click.self="reviewTarget = null">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">

          <!-- Header -->
          <div class="px-6 py-4 flex items-center gap-3"
            :style="reviewAction === 'approved'
              ? 'border-bottom:1px solid #A7F3D0;background:#F0FDF4;border-radius:1rem 1rem 0 0;'
              : 'border-bottom:1px solid #FECACA;background:#FEF2F2;border-radius:1rem 1rem 0 0;'"
          >
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
              :style="reviewAction === 'approved' ? 'background:#DCFCE7;' : 'background:#FEE2E2;'"
            >
              <CheckCircleIcon v-if="reviewAction === 'approved'" class="w-5 h-5" style="color:#15803D;" />
              <XCircleIcon     v-else                              class="w-5 h-5" style="color:#B91C1C;" />
            </div>
            <div>
              <h3 class="text-sm font-bold" :style="reviewAction === 'approved' ? 'color:#14532D;' : 'color:#7F1D1D;'">
                {{ reviewAction === 'approved' ? 'Approve Correction Request' : 'Deny Correction Request' }}
              </h3>
              <p class="text-xs" :style="reviewAction === 'approved' ? 'color:#166534;' : 'color:#991B1B;'">
                {{ reviewAction === 'approved' ? 'This will update the employee\'s DTR record.' : 'The employee will be notified and can re-submit.' }}
              </p>
            </div>
          </div>

          <!-- Details -->
          <div class="px-6 py-4 space-y-3">
            <div class="rounded-xl px-4 py-3 space-y-2" style="background:#F8FAFC;border:1px solid #E5E7EB;">
              <div class="flex justify-between items-center text-xs">
                <span style="color:#9CA3AF;">Employee</span>
                <span class="font-semibold" style="color:#0F172A;">{{ reviewTarget?.employee?.name }}</span>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span style="color:#9CA3AF;">Date</span>
                <span class="font-mono font-semibold" style="color:#0F172A;">{{ reviewTarget?.work_date_label }}</span>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span style="color:#9CA3AF;">Field</span>
                <span class="font-semibold" style="color:#0F172A;">{{ reviewTarget?.field_label }}</span>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span style="color:#9CA3AF;">Change</span>
                <div class="flex items-center gap-1.5">
                  <span class="font-mono px-1.5 py-0.5 rounded text-[11px]" style="background:#FEF2F2;color:#991B1B;">{{ reviewTarget?.original_value ?? 'none' }}</span>
                  <ArrowRightIcon class="w-3 h-3" style="color:#9CA3AF;" />
                  <span class="font-mono px-1.5 py-0.5 rounded text-[11px]" style="background:#ECFDF5;color:#065F46;">{{ reviewTarget?.requested_value }}</span>
                </div>
              </div>
              <div v-if="reviewTarget?.group_id" class="pt-1 flex items-center gap-1.5 text-xs" style="color:#7C3AED;">
                <LinkIcon class="w-3 h-3" />
                This is part of a multi-field batch — all related fields will be {{ reviewAction === 'approved' ? 'approved' : 'denied' }} together.
              </div>
              <div class="pt-1 text-xs">
                <p style="color:#9CA3AF;">Reason from employee:</p>
                <p class="mt-1 font-medium" style="color:#374151;">{{ reviewTarget?.reason }}</p>
              </div>
            </div>

            <div>
              <label class="modal-label">Reviewer Remarks <span style="color:#9CA3AF;">(optional)</span></label>
              <textarea
                v-model="reviewRemarks"
                rows="2"
                placeholder="Add a note for the employee…"
                class="modal-input resize-none"
                maxlength="500"
              ></textarea>
            </div>
          </div>

          <!-- Actions -->
          <div class="px-6 pb-5 flex gap-3">
            <button @click="reviewTarget = null" class="flex-1 py-2 rounded-lg text-sm font-semibold" style="background:#F1F5F9;color:#374151;">
              Cancel
            </button>
            <button
              @click="submitReview"
              :disabled="reviewing"
              class="flex-1 py-2 rounded-lg text-sm font-semibold disabled:opacity-50"
              :style="reviewAction === 'approved' ? 'background:#15803D;color:white;' : 'background:#B91C1C;color:white;'"
            >
              <span v-if="reviewing" class="flex items-center justify-center gap-2">
                <Loader2Icon class="w-4 h-4 animate-spin" />
                Processing…
              </span>
              <span v-else>{{ reviewAction === 'approved' ? 'Yes, Approve' : 'Yes, Deny' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Batch Review Modal ────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="batchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.5);" @click.self="batchModal = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">

          <!-- Header -->
          <div class="px-6 py-4 flex items-center gap-3"
            :style="batchAction === 'approved'
              ? 'border-bottom:1px solid #A7F3D0;background:#F0FDF4;border-radius:1rem 1rem 0 0;'
              : 'border-bottom:1px solid #FECACA;background:#FEF2F2;border-radius:1rem 1rem 0 0;'"
          >
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
              :style="batchAction === 'approved' ? 'background:#DCFCE7;' : 'background:#FEE2E2;'"
            >
              <CheckCircleIcon v-if="batchAction === 'approved'" class="w-5 h-5" style="color:#15803D;" />
              <XCircleIcon     v-else                             class="w-5 h-5" style="color:#B91C1C;" />
            </div>
            <div>
              <h3 class="text-sm font-bold" :style="batchAction === 'approved' ? 'color:#14532D;' : 'color:#7F1D1D;'">
                {{ batchAction === 'approved' ? 'Approve' : 'Deny' }} {{ selectedIds.size }} Request{{ selectedIds.size !== 1 ? 's' : '' }}
              </h3>
              <p class="text-xs" :style="batchAction === 'approved' ? 'color:#166534;' : 'color:#991B1B;'">
                {{ batchAction === 'approved'
                  ? 'All selected requests will be approved and DTR records updated.'
                  : 'All selected requests will be denied. Employees will be notified.' }}
              </p>
            </div>
          </div>

          <!-- Remarks -->
          <div class="px-6 py-4 space-y-3">
            <div class="rounded-xl px-4 py-3" style="background:#F8FAFC;border:1px solid #E5E7EB;">
              <p class="text-xs" style="color:#64748B;">
                <strong style="color:#0F172A;">{{ selectedIds.size }}</strong> correction request{{ selectedIds.size !== 1 ? 's' : '' }} selected.
                Batch corrections that share a group will also include their sibling fields automatically.
              </p>
            </div>
            <div>
              <label class="modal-label">Reviewer Remarks <span style="color:#9CA3AF;">(optional)</span></label>
              <textarea
                v-model="batchRemarks"
                rows="2"
                placeholder="Add a shared note for all employees…"
                class="modal-input resize-none"
                maxlength="500"
              ></textarea>
            </div>
          </div>

          <!-- Actions -->
          <div class="px-6 pb-5 flex gap-3">
            <button @click="batchModal = false" class="flex-1 py-2 rounded-lg text-sm font-semibold" style="background:#F1F5F9;color:#374151;">
              Cancel
            </button>
            <button
              @click="submitBatchReview"
              :disabled="batchReviewing"
              class="flex-1 py-2 rounded-lg text-sm font-semibold disabled:opacity-50"
              :style="batchAction === 'approved' ? 'background:#15803D;color:white;' : 'background:#B91C1C;color:white;'"
            >
              <span v-if="batchReviewing" class="flex items-center justify-center gap-2">
                <Loader2Icon class="w-4 h-4 animate-spin" />
                Processing…
              </span>
              <span v-else>Yes, {{ batchAction === 'approved' ? 'Approve' : 'Deny' }} All</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
  ClipboardEdit   as ClipboardEditIcon,
  Plus            as PlusIcon,
  Clock           as ClockIcon,
  CheckCircle     as CheckCircleIcon,
  XCircle         as XCircleIcon,
  AlertCircle     as AlertCircleIcon,
  Info            as InfoIcon,
  RefreshCw       as RefreshCwIcon,
  Loader2         as Loader2Icon,
  X               as XIcon,
  Check           as CheckIcon,
  ArrowRight      as ArrowRightIcon,
  Link            as LinkIcon,
} from 'lucide-vue-next';

const auth = useAuthStore();
const { toast } = useToast();
const isPrivileged = computed(() =>
  ['super_admin', 'admin', 'hr_officer'].includes(auth.employee?.role)
);

// ── Field definitions ──────────────────────────────────────────────
const FIELD_DEFS = [
  { key: 'morning_in',    label: 'Morning Time-In'    },
  { key: 'morning_out',   label: 'Morning Time-Out'   },
  { key: 'afternoon_in',  label: 'Afternoon Time-In'  },
  { key: 'afternoon_out', label: 'Afternoon Time-Out' },
];

function freshFieldState() {
  return Object.fromEntries(FIELD_DEFS.map(f => [f.key, { checked: false, value: '' }]));
}

// ── State ─────────────────────────────────────────────────────────
const corrections  = ref([]);
const summary      = ref({ pending: 0, approved: 0, denied: 0 });
const loading      = ref(false);
const activeTab    = ref(isPrivileged.value ? 'campus' : 'mine');
const statusFilter = ref('');

// Submit modal
const submitModal    = ref(false);
const submitting     = ref(false);
const submitError    = ref('');
const submitAttempted = ref(false);
const form = reactive({ work_date: '', reason: '', fields: freshFieldState() });

// Single review modal
const reviewTarget  = ref(null);
const reviewAction  = ref('approved');
const reviewRemarks = ref('');
const reviewing     = ref(false);

// Batch review
let _selectedIds = ref(new Set());
const selectedIds = _selectedIds;
const batchModal    = ref(false);
const batchAction   = ref('approved');
const batchRemarks  = ref('');
const batchReviewing = ref(false);

const today = new Date().toISOString().slice(0, 10);

const tabs = [
  { key: 'mine',   label: 'My Requests'     },
  { key: 'campus', label: 'Campus Requests' },
];

// ── Computed ──────────────────────────────────────────────────────
const noFieldsChecked = computed(() =>
  FIELD_DEFS.every(f => !form.fields[f.key].checked)
);

const pendingInView = computed(() =>
  corrections.value.filter(c => c.status === 'pending')
);

const allPendingSelected = computed(() =>
  pendingInView.value.length > 0 &&
  pendingInView.value.every(c => selectedIds.value.has(c.id))
);

const somePendingSelected = computed(() =>
  pendingInView.value.some(c => selectedIds.value.has(c.id))
);

// ── Helpers ───────────────────────────────────────────────────────
function initials(name = '') {
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function setFilter(status) {
  if (statusFilter.value === status) {
    statusFilter.value = '';
  } else {
    statusFilter.value = status;
    activeTab.value = 'mine';
  }
  load();
}

function switchTab(key) {
  activeTab.value = key;
  selectedIds.value = new Set();
  load();
}

// ── Batch select ──────────────────────────────────────────────────
function toggleSelect(id) {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function toggleSelectAll() {
  if (allPendingSelected.value) {
    selectedIds.value = new Set();
  } else {
    selectedIds.value = new Set(pendingInView.value.map(c => c.id));
  }
}

// ── Data ──────────────────────────────────────────────────────────
async function load() {
  loading.value = true;
  try {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;

    const res = await axios.get('/api/dtr-corrections', { params });
    summary.value = res.data.summary;

    const all = res.data.corrections;
    if (activeTab.value === 'mine') {
      corrections.value = all.filter(c => !c.employee || c.employee?.id === auth.employee?.id);
    } else {
      corrections.value = all;
    }
  } catch {
    toast('Failed to load correction requests.', 'error');
  } finally {
    loading.value = false;
  }
}

// ── Submit ────────────────────────────────────────────────────────
function openSubmit() {
  Object.assign(form, { work_date: '', reason: '', fields: freshFieldState() });
  submitError.value = '';
  submitAttempted.value = false;
  submitModal.value = true;
}

async function submitRequest() {
  submitAttempted.value = true;
  if (noFieldsChecked.value) return;

  const fields = FIELD_DEFS
    .filter(f => form.fields[f.key].checked)
    .map(f => ({ field: f.key, requested_value: form.fields[f.key].value }));

  submitting.value  = true;
  submitError.value = '';
  try {
    await axios.post('/api/dtr-corrections', {
      work_date: form.work_date,
      fields,
      reason:    form.reason,
    });
    submitModal.value = false;
    const count = fields.length;
    toast(`${count} correction field${count !== 1 ? 's' : ''} submitted. HR will review your request shortly.`);
    await load();
  } catch (e) {
    submitError.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? e.response?.data?.message
      ?? 'Failed to submit request.';
  } finally {
    submitting.value = false;
  }
}

// ── Single review ─────────────────────────────────────────────────
function openReview(correction, action) {
  reviewTarget.value  = correction;
  reviewAction.value  = action;
  reviewRemarks.value = '';
}

async function submitReview() {
  reviewing.value = true;
  try {
    await axios.patch(`/api/dtr-corrections/${reviewTarget.value.id}`, {
      status:           reviewAction.value,
      reviewer_remarks: reviewRemarks.value || null,
    });
    const label = reviewAction.value === 'approved' ? 'approved' : 'denied';
    toast(`Correction request ${label} successfully.`);
    reviewTarget.value = null;
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Review failed.', 'error');
    reviewTarget.value = null;
  } finally {
    reviewing.value = false;
  }
}

// ── Batch review ──────────────────────────────────────────────────
function openBatchReview(action) {
  batchAction.value  = action;
  batchRemarks.value = '';
  batchModal.value   = true;
}

async function submitBatchReview() {
  batchReviewing.value = true;
  try {
    await axios.post('/api/dtr-corrections/batch-review', {
      ids:              Array.from(selectedIds.value),
      status:           batchAction.value,
      reviewer_remarks: batchRemarks.value || null,
    });
    const count  = selectedIds.value.size;
    const label  = batchAction.value === 'approved' ? 'approved' : 'denied';
    batchModal.value  = false;
    selectedIds.value = new Set();
    toast(`${count} correction request${count !== 1 ? 's' : ''} ${label} successfully.`);
    await load();
  } catch (e) {
    toast(e.response?.data?.message ?? 'Batch review failed.', 'error');
    batchModal.value = false;
  } finally {
    batchReviewing.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.modal-label {
  display: block; font-size: 13px; font-weight: 600; color: #1E293B; margin-bottom: 5px;
}
.modal-input {
  width: 100%; padding: 8px 12px; font-size: 13px;
  border: 1px solid #E4E9F2; border-radius: 8px;
  color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.modal-input:focus {
  outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08);
}
.modal-input-sm {
  padding: 4px 8px; font-size: 12px;
  border: 1px solid #C7D2FE; border-radius: 6px;
  color: #0F172A; background: #fff;
  transition: border-color 0.15s;
}
.modal-input-sm:focus { outline: none; border-color: #0E1EC0; }

@media (max-width: 640px) {
  .grid.grid-cols-1.sm\\:grid-cols-3 { grid-template-columns: 1fr; }
  .max-w-screen-xl { padding-left: 12px; padding-right: 12px; }
}
</style>
