<template>
  <div class="flex flex-col h-full">

    <PageHeader title="Employees" subtitle="Manage faculty and staff accounts" :icon="UsersIcon">
      <button
        v-if="auth.isAdmin || auth.isHrOfficer"
        @click="openAdd"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors ph-btn-primary"
      >
        <PlusIcon class="w-4 h-4" />
        Add Employee
      </button>
    </PageHeader>

    <!-- Filters -->
    <div class="px-6 py-3 bg-white border-b border-[#E2E8F0] flex items-center gap-3 flex-wrap">
      <div class="relative">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#94A3B8]" />
        <input
          v-model="filters.search"
          placeholder="Search name, ID, position…"
          class="pl-9 pr-3 py-1.5 text-sm border border-[#E2E8F0] rounded-md w-56 focus:outline-none focus:ring-2 focus:ring-[#0E1EC0] text-[#0F172A] placeholder:text-[#94A3B8]"
        />
      </div>
      <select v-model="filters.department_id" class="select-sm">
        <option value="">All Departments</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <select v-model="filters.staff_type" class="select-sm">
        <option value="">All Staff Types</option>
        <optgroup label="Teaching">
          <option value="faculty">Faculty / Instructor</option>
        </optgroup>
        <optgroup label="Non-Teaching">
          <option value="administrative">Administrative Staff</option>
          <option value="security">Security Guard</option>
          <option value="custodial">Custodial / Utility Worker</option>
          <option value="maintenance">Maintenance Worker</option>
          <option value="laboratory">Laboratory Technician</option>
          <option value="driver">Driver</option>
          <option value="other">Other</option>
        </optgroup>
      </select>
      <select v-model="filters.role" class="select-sm">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="hr_officer">HR Officer</option>
        <option value="department_head">Department Head</option>
        <option value="employee">Faculty / Staff</option>
      </select>
      <select v-model="filters.status" class="select-sm">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <span class="ml-auto text-xs text-[#94A3B8]">{{ filtered.length }} record{{ filtered.length !== 1 ? 's' : '' }}</span>
      <span v-if="filtered.length > perPage" class="text-xs text-[#64748B]">
        — page {{ currentPage }} of {{ totalPages }}
      </span>
    </div>

    <!-- Bulk action bar -->
    <div v-if="canManage && selected.size" class="px-6 py-2.5 bg-[#EEF2FF] border-b border-[#D4DEFF] flex items-center gap-3">
      <span class="text-xs font-semibold text-[#0E1EC0]">{{ selected.size }} selected</span>
      <button @click="bulkActivate" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md border border-[#C7D2F6] text-[#0E1EC0] bg-white hover:bg-[#F4F6FF] transition-colors">
        <UserCheckIcon class="w-3.5 h-3.5" /> Activate
      </button>
      <button @click="bulkDeactivate" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-md border border-[#F3C7C7] text-[#DC2626] bg-white hover:bg-[#FEF2F2] transition-colors">
        <UserXIcon class="w-3.5 h-3.5" /> Deactivate
      </button>
      <button @click="selected = new Set()" class="text-xs text-[#64748B] hover:text-[#0F172A] ml-1">Clear</button>
    </div>

    <!-- Table -->
    <div class="flex-1 overflow-auto bg-[#F4F6FB] p-6">
      <div class="bg-white border border-[#E4E9F2] rounded-xl shadow-[0_2px_12px_rgba(8,14,128,0.07),0_1px_3px_rgba(8,14,128,0.04)]">
        <div class="overflow-x-auto rounded-xl">
        <table class="w-full text-sm emp-table">
          <thead class="sticky top-0 bg-white border-b border-[#E2E8F0] z-10">
            <tr>
              <th v-if="canManage" class="th-cell w-8">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-[#CBD5E1]" />
              </th>
              <th class="th-cell w-8">#</th>
              <th class="th-cell text-left th-sort" @click="sort('name')">
                <span class="inline-flex items-center gap-1">Employee <SortIcon :state="sortState('name')" /></span>
              </th>
              <th class="th-cell text-left th-sort" @click="sort('staff_type')">
                <span class="inline-flex items-center gap-1">Staff Type <SortIcon :state="sortState('staff_type')" /></span>
              </th>
              <th class="th-cell text-left th-sort" @click="sort('department')">
                <span class="inline-flex items-center gap-1">Department <SortIcon :state="sortState('department')" /></span>
              </th>
              <th class="th-cell text-left th-sort" @click="sort('position')">
                <span class="inline-flex items-center gap-1">Position <SortIcon :state="sortState('position')" /></span>
              </th>
              <th class="th-cell th-sort" @click="sort('role')">
                <span class="inline-flex items-center justify-center gap-1">Role <SortIcon :state="sortState('role')" /></span>
              </th>
              <th class="th-cell th-sort" @click="sort('status')">
                <span class="inline-flex items-center justify-center gap-1">Status <SortIcon :state="sortState('status')" /></span>
              </th>
              <th class="th-cell text-right" v-if="canManage">Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton rows -->
            <template v-if="loading">
              <tr v-for="i in 8" :key="'sk'+i" class="border-b border-[#E2E8F0]">
                <td v-if="canManage" class="td-cell text-center">
                  <div class="skel skel-text mx-auto" style="width:14px;"></div>
                </td>
                <td class="td-cell text-center">
                  <div class="skel skel-text mx-auto" style="width:18px;"></div>
                </td>
                <td class="td-cell">
                  <div class="flex items-center gap-3">
                    <div class="skel skel-avatar flex-shrink-0"></div>
                    <div class="skel-lines">
                      <div class="skel skel-text" style="width:120px;"></div>
                      <div class="skel skel-text" style="width:68px;height:9px;"></div>
                    </div>
                  </div>
                </td>
                <td class="td-cell"><div class="skel skel-text" style="width:80px;border-radius:12px;"></div></td>
                <td class="td-cell"><div class="skel skel-text" style="width:100px;"></div></td>
                <td class="td-cell"><div class="skel skel-text" style="width:90px;"></div></td>
                <td class="td-cell text-center"><div class="skel skel-text mx-auto" style="width:64px;border-radius:12px;"></div></td>
                <td class="td-cell text-center"><div class="skel skel-text mx-auto" style="width:50px;border-radius:12px;"></div></td>
                <td v-if="auth.isAdmin || auth.isHrOfficer" class="td-cell text-right">
                  <div class="skel skel-text ml-auto" style="width:44px;"></div>
                </td>
              </tr>
            </template>

            <tr
              v-else
              v-for="(emp, i) in paginated"
              :key="emp.id"
              class="border-b border-[#E2E8F0] last:border-0 hover:bg-[#F8FAFC] transition-colors"
              :class="{ 'opacity-50': !emp.is_active }"
            >
              <td v-if="canManage" class="td-cell text-center">
                <input type="checkbox" :checked="selected.has(emp.id)" @change="toggleSelect(emp.id)" class="rounded border-[#CBD5E1]" />
              </td>
              <td class="td-cell text-center text-[#94A3B8] font-mono text-xs">{{ (currentPage - 1) * perPage + i + 1 }}</td>
              <td class="td-cell">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 border border-[#E2E8F0]">
                    <img v-if="emp.photo_path" :src="`/storage/${emp.photo_path}`" :alt="emp.name" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                      <span class="text-white text-xs font-semibold">{{ initials(emp.name) }}</span>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium text-[#0F172A]">{{ emp.name }}</p>
                    <p class="text-xs text-[#94A3B8] font-mono">{{ emp.employee_id }}</p>
                    <p v-if="emp.fingerprint_id" class="text-[10px] text-[#0550A8] font-mono mt-0.5">
                      <span class="opacity-60">FP:</span> {{ emp.fingerprint_id }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="td-cell">
                <StaffTypeBadge v-if="emp.staff_type" :type="emp.staff_type" />
                <span v-else class="text-xs text-[#94A3B8]">—</span>
              </td>
              <td class="td-cell text-[#64748B]">{{ emp.department ?? '—' }}</td>
              <td class="td-cell text-[#64748B]">{{ emp.position ?? '—' }}</td>
              <td class="td-cell text-center"><RoleBadge :role="emp.role" /></td>
              <td class="td-cell text-center">
                <span :class="emp.is_active ? 'badge-green' : 'badge-gray'">
                  {{ emp.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="td-cell text-right" v-if="auth.isAdmin || auth.isHrOfficer">
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="router.push({ name: 'reports', query: { employee: emp.employee_id } })"
                    class="btn-icon"
                    title="Quick Form 48"
                  >
                    <FileTextIcon class="w-4 h-4" />
                  </button>
                  <button @click="openEdit(emp)" class="btn-icon" title="Edit">
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <!-- Deactivating is reversible with one click (the Activate
                       button right below), so it fires immediately with an
                       Undo toast instead of an "are you sure?" interruption. -->
                  <button v-if="emp.is_active" @click="deactivate(emp)" class="btn-icon text-[#EF4444]" title="Deactivate">
                    <UserXIcon class="w-4 h-4" />
                  </button>
                  <button v-else @click="activate(emp)" class="btn-icon text-[#10B981]" title="Activate">
                    <UserCheckIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && paginated.length === 0">
              <td :colspan="canManage ? 9 : 7">
                <EmptyState
                  title="No employees found"
                  description="Try adjusting your search or filters, or add a new employee."
                />
              </td>
            </tr>
          </tbody>
        </table>
        </div><!-- /overflow-x-auto -->
      </div>

      <PaginationBar
        v-if="totalPages > 1"
        :current-page="currentPage"
        :last-page="totalPages"
        :total="filtered.length"
        :from="(currentPage - 1) * perPage + 1"
        :to="Math.min(currentPage * perPage, filtered.length)"
        @go="currentPage = $event"
      />
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       Add / Edit Modal
  ═══════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="requestCloseModal" />
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">

        <!-- ── Gradient Header ── -->
        <div class="flex-shrink-0 bg-gradient-to-r from-[#0A1540] via-[#0F1F5C] to-[#0E1EC0] px-6 py-5 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <!-- Live avatar -->
            <div class="w-12 h-12 rounded-xl overflow-hidden border-2 border-white/20 flex-shrink-0">
              <img v-if="photoPreview || (modal.editing && editingEmp?.photo_path)"
                :src="photoPreview || `/storage/${editingEmp?.photo_path}`"
                class="w-full h-full object-cover" alt="Employee photo" />
              <div v-else class="w-full h-full bg-white/10 flex items-center justify-center">
                <span class="text-white font-bold text-base tracking-wide">{{ avatarInitials }}</span>
              </div>
            </div>
            <div>
              <h2 class="text-white font-semibold text-base leading-tight">
                {{ modal.editing ? 'Edit Employee Profile' : 'Add New Employee' }}
              </h2>
              <p class="text-white/50 text-xs mt-0.5">
                {{ modal.editing
                  ? ((form.first_name + ' ' + form.last_name).trim() || 'Employee Profile')
                  : 'Complete all sections to register a new employee' }}
              </p>
            </div>
          </div>
          <button @click="requestCloseModal" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/70 hover:text-white transition-all">
            <XIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- ── Body: sidebar + scrollable form ── -->
        <div class="flex flex-1 min-h-0 overflow-hidden">

          <!-- Left sidebar nav (hidden on small screens) -->
          <nav class="modal-sidebar w-44 flex-shrink-0 bg-[#F8FAFC] border-r border-[#E2E8F0] py-3 flex flex-col gap-px overflow-y-auto">
            <button
              v-for="sec in formSections"
              :key="sec.id"
              type="button"
              @click="scrollToSection(sec.id)"
              class="group w-full flex items-center gap-2.5 px-3 py-2.5 text-left transition-all relative"
              :class="activeSection === sec.id
                ? 'bg-white text-[#0E1EC0] after:absolute after:right-0 after:top-1 after:bottom-1 after:w-0.5 after:bg-[#0E1EC0] after:rounded-l'
                : 'text-[#64748B] hover:bg-white/70 hover:text-[#0F172A]'"
            >
              <span
                class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0 text-xs font-bold transition-all"
                :class="activeSection === sec.id
                  ? 'bg-[#0E1EC0] text-white shadow-sm'
                  : sectionDone(sec.id)
                    ? 'bg-emerald-100 text-emerald-600'
                    : 'bg-[#E8EDF5] text-[#94A3B8] group-hover:bg-[#D8E0F5] group-hover:text-[#0E1EC0]'"
              >
                <CheckIcon v-if="sectionDone(sec.id) && activeSection !== sec.id" class="w-3 h-3" />
                <span v-else>{{ sec.num }}</span>
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold leading-tight truncate">{{ sec.label }}</p>
                <p class="text-[10px] mt-0.5 truncate" :class="sectionDone(sec.id) ? 'text-emerald-500' : 'text-[#94A3B8]'">
                  {{ sectionDone(sec.id) ? 'Complete' : sec.hint }}
                </p>
              </div>
            </button>

            <!-- Progress bar at bottom of sidebar -->
            <div class="mt-auto mx-3 pt-3 border-t border-[#E2E8F0]">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] text-[#94A3B8] font-medium">Progress</span>
                <span class="text-[10px] font-bold text-[#0E1EC0]">{{ completedSections }}/{{ formSections.length }}</span>
              </div>
              <div class="h-1.5 bg-[#E2E8F0] rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-[#0E1EC0] to-[#4F6EF7] rounded-full transition-all duration-500"
                  :style="{ width: (completedSections / formSections.length * 100) + '%' }"></div>
              </div>
            </div>
          </nav>

          <!-- Scrollable form content -->
          <div ref="formBody" class="flex-1 overflow-y-auto" @scroll="onScroll">
            <form @submit.prevent="submit" id="emp-form" class="pb-8">

              <!-- ①  Account Information -->
              <section id="sec-account" class="px-6 pt-6 pb-4">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-9 h-9 rounded-xl bg-[#EEF2FF] flex items-center justify-center flex-shrink-0">
                    <KeyRoundIcon class="w-4.5 h-4.5 text-[#0E1EC0]" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-[#0F172A]">Account Information</p>
                    <p class="text-xs text-[#94A3B8] mt-0.5">Login credentials and system access level</p>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="form-label">Employee ID <span class="req">*</span></label>
                    <input v-model="form.employee_id" class="form-input" required :disabled="modal.editing" placeholder="e.g. CCS-001" />
                    <p v-if="modal.editing" class="field-hint">Cannot be changed after creation.</p>
                  </div>
                  <div>
                    <label class="form-label">System Role <span class="req">*</span></label>
                    <select v-model="form.role" class="form-input" required>
                      <option value="employee">Faculty / Staff</option>
                      <option value="department_head">Department Head</option>
                      <option value="hr_officer">HR Officer</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>
                  <div class="col-span-2">
                    <label class="form-label">
                      {{ modal.editing ? 'New Password' : 'Password' }}
                      <span v-if="!modal.editing" class="req">*</span>
                      <span v-else class="text-[#94A3B8] font-normal text-xs ml-1">(leave blank to keep current)</span>
                    </label>
                    <div class="relative">
                      <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="form-input pr-10"
                        :required="!modal.editing"
                        placeholder="8+ characters, with letters and numbers"
                      />
                      <button type="button" @click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#94A3B8] hover:text-[#0F172A] transition-colors">
                        <EyeOffIcon v-if="showPassword" class="w-4 h-4" />
                        <EyeIcon v-else class="w-4 h-4" />
                      </button>
                    </div>
                    <!-- Password strength meter -->
                    <div v-if="form.password" class="mt-2 flex items-center gap-2.5">
                      <div class="flex gap-1 flex-1">
                        <div v-for="i in 4" :key="i"
                          class="h-1 flex-1 rounded-full transition-all duration-300"
                          :class="passwordStrength >= i ? strengthBg : 'bg-[#E2E8F0]'">
                        </div>
                      </div>
                      <span class="text-xs font-medium" :class="strengthText">{{ strengthLabel }}</span>
                    </div>
                  </div>
                </div>
              </section>

              <div class="mx-6 border-t border-dashed border-[#E8EDF5]"></div>

              <!-- ②  Personal Information -->
              <section id="sec-personal" class="px-6 pt-5 pb-4">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0">
                    <UserCircleIcon class="w-4.5 h-4.5 text-purple-600" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-[#0F172A]">Personal Information</p>
                    <p class="text-xs text-[#94A3B8] mt-0.5">Full name, contact details and demographics</p>
                  </div>
                </div>

                <!-- Photo upload -->
                <div class="mb-5 flex items-center gap-4">
                  <div class="w-16 h-16 rounded-xl overflow-hidden border-2 border-[#E2E8F0] flex-shrink-0 bg-[#F8FAFC]">
                    <img v-if="photoPreview || (modal.editing && editingEmp?.photo_path)"
                      :src="photoPreview || `/storage/${editingEmp?.photo_path}`"
                      class="w-full h-full object-cover" alt="Photo preview" />
                    <div v-else class="w-full h-full flex items-center justify-center">
                      <UserCircleIcon class="w-8 h-8 text-[#CBD5E1]" />
                    </div>
                  </div>
                  <div class="flex flex-col gap-1.5">
                    <label class="form-label !mb-0">Profile Photo</label>
                    <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium border border-[#E2E8F0] rounded-lg text-[#374151] hover:bg-[#F8FAFC] transition-colors w-fit">
                      <CameraIcon class="w-3.5 h-3.5" />
                      {{ photoFile ? photoFile.name : 'Choose Photo' }}
                      <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onPhotoChange" />
                    </label>
                    <p class="text-[10px] text-[#94A3B8]">JPG, PNG or WebP · max 2 MB · saved when you submit</p>
                  </div>
                </div>

                <div class="grid grid-cols-6 gap-3">
                  <div class="col-span-2">
                    <label class="form-label">First Name <span class="req">*</span></label>
                    <input v-model="form.first_name" class="form-input" required placeholder="Juan" />
                  </div>
                  <div class="col-span-2">
                    <label class="form-label">Last Name <span class="req">*</span></label>
                    <input v-model="form.last_name" class="form-input" required placeholder="Dela Cruz" />
                  </div>
                  <div class="col-span-1">
                    <label class="form-label">Middle Name</label>
                    <input v-model="form.middle_name" class="form-input" placeholder="Optional" />
                  </div>
                  <div class="col-span-1">
                    <label class="form-label">Suffix</label>
                    <select v-model="form.suffix" class="form-input">
                      <option value="">—</option>
                      <option>Jr.</option><option>Sr.</option>
                      <option>II</option><option>III</option><option>IV</option>
                    </select>
                  </div>

                  <!-- Gender pill buttons -->
                  <div class="col-span-2">
                    <label class="form-label">Gender</label>
                    <div class="flex gap-2 mt-0.5">
                      <button
                        v-for="g in [{ v: 'male', l: 'Male' }, { v: 'female', l: 'Female' }]"
                        :key="g.v"
                        type="button"
                        @click="form.gender = form.gender === g.v ? '' : g.v"
                        class="flex-1 py-2 text-xs font-medium rounded-lg border transition-all"
                        :class="form.gender === g.v
                          ? 'border-[#0E1EC0] bg-[#EEF2FF] text-[#0E1EC0]'
                          : 'border-[#E2E8F0] text-[#64748B] hover:border-[#C7D2F6] hover:bg-[#F8FAFF]'"
                      >{{ g.l }}</button>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <label class="form-label">Civil Status</label>
                    <select v-model="form.civil_status" class="form-input">
                      <option value="">— Select —</option>
                      <option value="single">Single</option>
                      <option value="married">Married</option>
                      <option value="widowed">Widowed</option>
                      <option value="separated">Separated</option>
                    </select>
                  </div>
                  <div class="col-span-2">
                    <label class="form-label">Date of Birth</label>
                    <input v-model="form.date_of_birth" type="date" class="form-input" />
                  </div>
                  <div class="col-span-3">
                    <label class="form-label">Contact Number</label>
                    <input v-model="form.contact_number" class="form-input" placeholder="e.g. 09XX-XXX-XXXX" />
                  </div>
                  <div class="col-span-3">
                    <label class="form-label">Email Address</label>
                    <input v-model="form.email" type="email" class="form-input" placeholder="Optional" />
                  </div>
                </div>
              </section>

              <div class="mx-6 border-t border-dashed border-[#E8EDF5]"></div>

              <!-- ③  Employment Details -->
              <section id="sec-employment" class="px-6 pt-5 pb-4">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <BriefcaseIcon class="w-4.5 h-4.5 text-amber-600" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-[#0F172A]">Employment Details</p>
                    <p class="text-xs text-[#94A3B8] mt-0.5">Staff type, department, shift and hire date</p>
                  </div>
                </div>

                <!-- Staff Type grid -->
                <div class="mb-4">
                  <label class="form-label">Staff Type <span class="req">*</span></label>
                  <div class="grid grid-cols-4 gap-2 mt-1.5">
                    <button
                      v-for="st in staffTypes"
                      :key="st.value"
                      type="button"
                      @click="form.staff_type = form.staff_type === st.value ? '' : st.value"
                      class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border text-xs font-medium transition-all"
                      :class="form.staff_type === st.value
                        ? 'border-[#0E1EC0] bg-[#EEF2FF] text-[#0E1EC0] shadow-sm ring-1 ring-[#0E1EC0]/20'
                        : 'border-[#E2E8F0] text-[#64748B] hover:border-[#C7D2F6] hover:bg-[#F8FAFF] hover:text-[#0F172A]'"
                    >
                      <component :is="st.icon" class="w-5 h-5" />
                      {{ st.label }}
                    </button>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="form-label">Position / Designation</label>
                    <input
                      v-model="form.position"
                      class="form-input"
                      list="pos-suggestions"
                      placeholder="Type or select a position…"
                      autocomplete="off"
                    />
                    <datalist id="pos-suggestions">
                      <option v-for="p in suggestedPositions" :key="p" :value="p" />
                    </datalist>
                    <p class="field-hint">
                      {{ form.staff_type
                        ? `Showing ${suggestedPositions.length} positions for ${selectedStaffTypeLabel}`
                        : 'Select a staff type above to see relevant positions' }}
                    </p>
                  </div>
                  <div>
                    <label class="form-label">Employment Status</label>
                    <select v-model="form.employment_status" class="form-input">
                      <option value="">— Select —</option>
                      <option value="permanent">Permanent</option>
                      <option value="casual">Casual</option>
                      <option value="contractual">Contractual</option>
                      <option value="part-time">Part-time</option>
                    </select>
                  </div>
                  <div>
                    <label class="form-label">Department</label>
                    <select v-model="form.department_id" class="form-input">
                      <option value="">— None —</option>
                      <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                  </div>

                  <!-- Smart Work Shift -->
                  <div>
                    <label class="form-label">Work Shift</label>
                    <select v-model="form.shift_id" class="form-input">
                      <option value="">— None —</option>
                      <template v-if="shiftGroups">
                        <optgroup v-if="shiftGroups.recommended.length" :label="`✓ Recommended for ${selectedStaffTypeLabel}`">
                          <option v-for="s in shiftGroups.recommended" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </optgroup>
                        <optgroup v-if="shiftGroups.general.length" label="General (All Types)">
                          <option v-for="s in shiftGroups.general" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </optgroup>
                        <optgroup v-if="shiftGroups.other.length" label="Other Shifts">
                          <option v-for="s in shiftGroups.other" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </optgroup>
                      </template>
                      <template v-else>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                      </template>
                    </select>

                    <!-- No recommended shifts + quick create -->
                    <div v-if="shiftGroups && !shiftGroups.recommended.length" class="mt-1.5">
                      <div class="flex items-center justify-between gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-2.5 py-1.5">
                        <span class="flex items-center gap-1.5">
                          <AlertCircleIcon class="w-3.5 h-3.5 flex-shrink-0" />
                          No {{ selectedStaffTypeLabel }} shifts found.
                        </span>
                        <button type="button" @click="openQuickShift"
                          class="flex-shrink-0 flex items-center gap-1 font-semibold text-[#0E1EC0] hover:underline">
                          <PlusIcon class="w-3 h-3" /> Create Shift
                        </button>
                      </div>
                      <div v-if="quickShift.open" class="mt-2 border border-[#E2E8F0] rounded-lg bg-[#F8FAFC] p-3 space-y-2.5">
                        <p class="text-xs font-semibold text-[#0F172A]">Quick Create — {{ selectedStaffTypeLabel }} Shift</p>
                        <div>
                          <label class="form-label !text-xs">Shift Name</label>
                          <input v-model="quickShift.name" class="form-input !py-1.5 !text-xs" placeholder="e.g. Faculty Regular Shift" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                          <div><label class="form-label !text-xs">Morning In</label><input v-model="quickShift.morning_in" type="time" class="form-input !py-1.5 !text-xs" /></div>
                          <div><label class="form-label !text-xs">Morning Out</label><input v-model="quickShift.morning_out" type="time" class="form-input !py-1.5 !text-xs" /></div>
                          <div><label class="form-label !text-xs">Afternoon In</label><input v-model="quickShift.afternoon_in" type="time" class="form-input !py-1.5 !text-xs" /></div>
                          <div><label class="form-label !text-xs">Afternoon Out</label><input v-model="quickShift.afternoon_out" type="time" class="form-input !py-1.5 !text-xs" /></div>
                          <div><label class="form-label !text-xs">Grace (min)</label><input v-model.number="quickShift.grace_period_minutes" type="number" min="0" max="60" class="form-input !py-1.5 !text-xs" /></div>
                          <div><label class="form-label !text-xs">Req. Hours</label><input v-model.number="quickShift.required_hours" type="number" min="1" max="24" step="0.5" class="form-input !py-1.5 !text-xs" /></div>
                        </div>
                        <p v-if="quickShift.error" class="text-xs text-red-600">{{ quickShift.error }}</p>
                        <div class="flex justify-end gap-2 pt-0.5">
                          <button type="button" @click="quickShift.open = false" class="px-3 py-1.5 text-xs border border-[#E2E8F0] rounded-md text-[#64748B] hover:bg-white transition-colors">Cancel</button>
                          <button type="button" @click="saveQuickShift" :disabled="quickShift.saving || !quickShift.name"
                            class="px-3 py-1.5 text-xs font-medium bg-[#0E1EC0] text-white rounded-md hover:bg-[#1228CC] disabled:opacity-60 transition-colors">
                            {{ quickShift.saving ? 'Saving…' : 'Save Shift' }}
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Selected shift preview -->
                    <div v-if="selectedShift" class="mt-1.5 flex items-center flex-wrap gap-x-2.5 gap-y-1 bg-[#F8FAFC] border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs text-[#64748B]">
                      <ClockIcon class="w-3.5 h-3.5 text-[#94A3B8] flex-shrink-0" />
                      <span>A.M. <span class="font-mono font-medium text-[#0F172A]">{{ selectedShift.morning_in?.slice(0,5) }}–{{ selectedShift.morning_out?.slice(0,5) }}</span></span>
                      <span class="text-[#CBD5E1]">|</span>
                      <span>P.M. <span class="font-mono font-medium text-[#0F172A]">{{ selectedShift.afternoon_in?.slice(0,5) }}–{{ selectedShift.afternoon_out?.slice(0,5) }}</span></span>
                      <span class="text-[#CBD5E1]">|</span>
                      <span>Grace <span class="font-medium text-[#0F172A]">{{ selectedShift.grace_period_minutes }}m</span></span>
                    </div>
                  </div>

                  <div>
                    <label class="form-label">Date Hired</label>
                    <input v-model="form.date_hired" type="date" class="form-input" />
                  </div>
                </div>
              </section>

              <div class="mx-6 border-t border-dashed border-[#E8EDF5]"></div>

              <!-- ④  Government IDs -->
              <section id="sec-govids" class="px-6 pt-5 pb-4">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                    <CreditCardIcon class="w-4.5 h-4.5 text-emerald-600" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-[#0F172A]">Government ID Numbers</p>
                    <p class="text-xs text-[#94A3B8] mt-0.5">Required for official records and CSC Form 48</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="form-label">GSIS Number</label>
                    <input v-model="form.gsis_number" class="form-input font-mono" placeholder="0000-000000-0" />
                  </div>
                  <div>
                    <label class="form-label">PhilHealth Number</label>
                    <input v-model="form.philhealth_number" class="form-input font-mono" placeholder="00-000000000-0" />
                  </div>
                  <div>
                    <label class="form-label">Pag-IBIG / HDMF Number</label>
                    <input v-model="form.pagibig_number" class="form-input font-mono" placeholder="0000-0000-0000" />
                  </div>
                  <div>
                    <label class="form-label">TIN</label>
                    <input v-model="form.tin_number" class="form-input font-mono" placeholder="000-000-000-000" />
                  </div>
                </div>
              </section>

              <div class="mx-6 border-t border-dashed border-[#E8EDF5]"></div>

              <!-- ⑤  Biometric -->
              <section id="sec-biometric" class="px-6 pt-5 pb-4">
                <div class="flex items-center gap-3 mb-5">
                  <div class="w-9 h-9 rounded-xl bg-cyan-50 flex items-center justify-center flex-shrink-0">
                    <FingerprintIcon class="w-4.5 h-4.5 text-cyan-600" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-[#0F172A]">Biometric Device</p>
                    <p class="text-xs text-[#94A3B8] mt-0.5">ZKTeco / GRANDING fingerprint terminal enrollment</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="form-label">Fingerprint ID (Device PIN)</label>
                    <input v-model="form.fingerprint_id" class="form-input font-mono" placeholder="e.g. 1" maxlength="20" />
                    <p class="field-hint">Numeric User ID assigned to this employee on the device.</p>
                  </div>
                </div>
              </section>

            </form>
          </div>
        </div>

        <!-- ── Footer ── -->
        <div class="flex-shrink-0 px-6 py-4 border-t border-[#E2E8F0] bg-[#F8FAFC] flex items-center justify-between">
          <p v-if="error" class="text-sm text-red-600 flex items-center gap-1.5">
            <AlertCircleIcon class="w-4 h-4 flex-shrink-0" />{{ error }}
          </p>
          <p v-else class="text-xs text-[#94A3B8]">Fields marked <span class="text-red-500 font-bold">*</span> are required</p>
          <div class="flex gap-3">
            <button type="button" @click="requestCloseModal" class="btn-outline">Cancel</button>
            <button type="submit" form="emp-form" :disabled="saving" class="btn-primary">
              <Loader2Icon v-if="saving" class="w-4 h-4 animate-spin" />
              {{ modal.editing ? 'Save Changes' : 'Add Employee' }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToast } from '@/composables/useToast';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';
import { useSortable } from '@/composables/useSortable';
import { usePersistedFilters } from '@/composables/usePersistedFilters';
import RoleBadge from '@/components/RoleBadge.vue';
import StaffTypeBadge from '@/components/StaffTypeBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import SortIcon from '@/components/SortIcon.vue';
import {
  Plus as PlusIcon, Search as SearchIcon, Pencil as PencilIcon, Users as UsersIcon,
  UserX as UserXIcon, UserCheck as UserCheckIcon,
  X as XIcon, Loader2 as Loader2Icon, Check as CheckIcon,
  FileText as FileTextIcon,
  GraduationCap as GraduationCapIcon,
  Briefcase as BriefcaseIcon,
  Shield as ShieldIcon,
  Sparkles as SparklesIcon,
  Wrench as WrenchIcon,
  FlaskConical as FlaskConicalIcon,
  Car as CarIcon,
  MoreHorizontal as MoreHorizontalIcon,
  AlertCircle as AlertCircleIcon,
  Clock as ClockIcon,
  KeyRound as KeyRoundIcon,
  UserCircle as UserCircleIcon,
  Eye as EyeIcon,
  EyeOff as EyeOffIcon,
  CreditCard as CreditCardIcon,
  Fingerprint as FingerprintIcon,
  Camera as CameraIcon,
} from 'lucide-vue-next';

const auth        = useAuthStore();
const router      = useRouter();
const route       = useRoute();
const { toast }   = useToast();
const unsavedGuard = useUnsavedGuard();

const canManage = computed(() => auth.isAdmin || auth.isHrOfficer);

const employees   = ref([]);
const departments = ref([]);
const shifts      = ref([]);
const loading     = ref(false);
const saving      = ref(false);
const error       = ref('');
const showPassword  = ref(false);
const photoFile     = ref(null);
const photoPreview  = ref(null);
const editingEmp    = ref(null);
const formBody      = ref(null);
const activeSection = ref('account');

const filters     = reactive({ search: route.query.q ?? '', department_id: '', staff_type: '', role: '', status: '' });
usePersistedFilters('employees-filters', filters, ['search']);

// A department deep-link (e.g. clicking a row in the Dashboard's department
// ranking) should win over whatever filter was persisted from a previous visit.
if (route.query.department) {
  filters.department_id = Number(route.query.department);
}
const currentPage = ref(1);
const perPage     = 20;
const selected    = ref(new Set());

const staffTypes = [
  { value: 'faculty',        label: 'Faculty / Instructor', icon: GraduationCapIcon  },
  { value: 'administrative', label: 'Administrative',       icon: BriefcaseIcon      },
  { value: 'security',       label: 'Security Guard',       icon: ShieldIcon         },
  { value: 'custodial',      label: 'Custodial / Utility',  icon: SparklesIcon       },
  { value: 'maintenance',    label: 'Maintenance',          icon: WrenchIcon         },
  { value: 'laboratory',     label: 'Lab Technician',       icon: FlaskConicalIcon   },
  { value: 'driver',         label: 'Driver',               icon: CarIcon            },
  { value: 'other',          label: 'Other',                icon: MoreHorizontalIcon },
];

const positionsByType = {
  faculty: [
    'Instructor I', 'Instructor II', 'Instructor III',
    'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Assistant Professor IV',
    'Associate Professor I', 'Associate Professor II', 'Associate Professor III', 'Associate Professor IV', 'Associate Professor V',
    'Professor I', 'Professor II', 'Professor III', 'Professor IV', 'Professor V', 'Professor VI',
    'University Professor', 'Lecturer', 'Program Head', 'Department Chairperson', 'College Dean',
  ],
  administrative: [
    'Administrative Aide I', 'Administrative Aide II', 'Administrative Aide III',
    'Administrative Aide IV', 'Administrative Aide V', 'Administrative Aide VI',
    'Administrative Assistant I', 'Administrative Assistant II', 'Administrative Assistant III',
    'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III',
    'Administrative Officer IV', 'Administrative Officer V',
    'Accountant I', 'Accountant II', 'Accountant III',
    'Budget Officer I', 'Budget Officer II', 'Budget Officer III',
    'Cashier I', 'Cashier II', 'Cashier III',
    'Computer Programmer I', 'Computer Programmer II',
    'Human Resource Management Officer I', 'Human Resource Management Officer II', 'Human Resource Management Officer III',
    'Information Technology Officer I', 'Information Technology Officer II',
    'Librarian I', 'Librarian II', 'Librarian III',
    'Nurse I', 'Nurse II', 'Nurse III',
    'Planning Officer I', 'Planning Officer II', 'Planning Officer III',
    'Records Officer I', 'Records Officer II', 'Records Officer III',
    'Supply Officer I', 'Supply Officer II', 'Supply Officer III',
    'University Registrar I', 'University Registrar II', 'University Registrar III',
    'Campus Director', 'Vice President for Academic Affairs',
    'Vice President for Administration and Finance',
    'Vice President for Research, Extension and Training', 'University President',
  ],
  security: [
    'Security Guard I', 'Security Guard II', 'Security Guard III',
    'Security Officer I', 'Security Officer II', 'Chief Security Guard',
  ],
  custodial: [
    'Utility Worker I', 'Utility Worker II',
    'Grounds Maintenance Worker', 'Laborer I', 'Laborer II',
    'Housekeeper I', 'Housekeeper II', 'Custodial Worker',
  ],
  maintenance: [
    'Carpenter I', 'Carpenter II',
    'Electrician I', 'Electrician II',
    'Equipment Operator I', 'Equipment Operator II',
    'Maintenance Worker I', 'Maintenance Worker II',
    'Mechanic I', 'Mechanic II',
    'Painter I', 'Painter II',
    'Plumber I', 'Plumber II',
    'Welder / Fabricator',
  ],
  laboratory: [
    'Laboratory Aide', 'Laboratory Technician I', 'Laboratory Technician II', 'Laboratory Technician III',
    'Laboratory Equipment Technician I', 'Laboratory Equipment Technician II',
    'Science Research Specialist I', 'Science Research Specialist II',
  ],
  driver: [
    'Driver I', 'Driver II',
    'Heavy Equipment Operator I', 'Heavy Equipment Operator II',
    'Motor Vehicle Operator',
  ],
  other: [
    'Dentist I', 'Dentist II',
    'Extension Specialist I', 'Extension Specialist II',
    'Gender and Development Officer',
    'Guidance Counselor I', 'Guidance Counselor II',
    'Physician I', 'Physician II',
    'Project Development Officer I', 'Project Development Officer II',
    'Researcher I', 'Researcher II',
    'Research Specialist I', 'Research Specialist II',
    'Social Welfare Officer I', 'Social Welfare Officer II',
    'Sports Coordinator', 'Student Affairs Officer',
  ],
};

const formSections = [
  { id: 'account',    num: '1', label: 'Account',       hint: 'ID & password'   },
  { id: 'personal',   num: '2', label: 'Personal Info', hint: 'Name & contact'  },
  { id: 'employment', num: '3', label: 'Employment',    hint: 'Type & shift'    },
  { id: 'govids',     num: '4', label: 'Gov. IDs',      hint: 'GSIS, PhilHealth' },
  { id: 'biometric',  num: '5', label: 'Biometric',     hint: 'Fingerprint ID'  },
];

const modal = reactive({ open: false, editing: false });
const form  = reactive({
  employee_id: '', first_name: '', last_name: '', middle_name: '', suffix: '',
  gender: '', civil_status: '', date_of_birth: '',
  email: '', password: '', role: 'employee',
  staff_type: '', position: '', employment_status: '',
  department_id: '', shift_id: '',
  contact_number: '', date_hired: '',
  gsis_number: '', philhealth_number: '', pagibig_number: '', tin_number: '',
  fingerprint_id: '',
});

// ── Filtering & pagination ──────────────────────────────────────────
const filtered = computed(() => {
  let list = employees.value;
  const s = filters.search.toLowerCase();
  if (s) list = list.filter(e =>
    e.name.toLowerCase().includes(s) ||
    e.employee_id.toLowerCase().includes(s) ||
    (e.position ?? '').toLowerCase().includes(s)
  );
  if (filters.department_id) list = list.filter(e => e.department_id === filters.department_id);
  if (filters.staff_type)    list = list.filter(e => e.staff_type === filters.staff_type);
  if (filters.role)          list = list.filter(e => e.role === filters.role);
  if (filters.status === 'active')   list = list.filter(e => e.is_active);
  if (filters.status === 'inactive') list = list.filter(e => !e.is_active);
  return list;
});
watch(filters, () => { currentPage.value = 1; });

const { sortKey, sortDir, sort, sorted } = useSortable(filtered, {
  name:       (e) => e.name?.toLowerCase() ?? '',
  staff_type: (e) => e.staff_type ?? '',
  department: (e) => e.department ?? '',
  position:   (e) => e.position ?? '',
  role:       (e) => e.role ?? '',
  status:     (e) => (e.is_active ? 0 : 1),
});
function sortState(key) {
  return sortKey.value === key ? sortDir.value : null;
}

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));
const paginated  = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return sorted.value.slice(start, start + perPage);
});

// ── Selection & bulk actions ─────────────────────────────────────────
const allSelected = computed(() =>
  paginated.value.length > 0 && paginated.value.every(e => selected.value.has(e.id))
);
function toggleSelectAll() {
  const next = new Set(selected.value);
  if (allSelected.value) paginated.value.forEach(e => next.delete(e.id));
  else                    paginated.value.forEach(e => next.add(e.id));
  selected.value = next;
}
function toggleSelect(id) {
  const next = new Set(selected.value);
  if (next.has(id)) next.delete(id); else next.add(id);
  selected.value = next;
}

async function bulkDeactivate() {
  const targets = employees.value.filter(e => selected.value.has(e.id) && e.is_active);
  if (!targets.length) {
    toast('The selected employees are already inactive.', 'warning');
    return;
  }
  // Reversible in one click (Undo below, or Activate any time after) — fires
  // immediately instead of an "are you sure?" dialog, same as the row-level
  // deactivate button.
  const results = await Promise.allSettled(targets.map(e => axios.delete(`/api/employees/${e.id}`)));
  const deactivated = [];
  results.forEach((r, i) => { if (r.status === 'fulfilled') { targets[i].is_active = false; deactivated.push(targets[i]); } });
  const okCount = deactivated.length;
  toast(
    `${okCount} employee${okCount !== 1 ? 's' : ''} deactivated.`,
    okCount === targets.length ? 'success' : 'warning',
    6000,
    okCount ? { actionLabel: 'Undo', onAction: () => reactivateBatch(deactivated) } : {},
  );
  selected.value = new Set();
}

async function reactivateBatch(emps) {
  const results = await Promise.allSettled(emps.map(e => axios.put(`/api/employees/${e.id}`, { is_active: true })));
  let okCount = 0;
  results.forEach((r, i) => { if (r.status === 'fulfilled') { emps[i].is_active = true; okCount++; } });
  toast(`${okCount} employee${okCount !== 1 ? 's' : ''} reactivated.`, 'success');
}

async function bulkActivate() {
  const targets = employees.value.filter(e => selected.value.has(e.id) && !e.is_active);
  if (!targets.length) {
    toast('The selected employees are already active.', 'warning');
    return;
  }
  const results = await Promise.allSettled(
    targets.map(e => axios.put(`/api/employees/${e.id}`, { is_active: true }))
  );
  let okCount = 0;
  results.forEach((r, i) => { if (r.status === 'fulfilled') { targets[i].is_active = true; okCount++; } });
  toast(`${okCount} employee${okCount !== 1 ? 's' : ''} activated.`, okCount === targets.length ? 'success' : 'warning');
  selected.value = new Set();
}

// ── Modal nav & header ──────────────────────────────────────────────
const avatarInitials = computed(() => {
  const f = form.first_name?.trim()[0] ?? '';
  const l = form.last_name?.trim()[0] ?? '';
  return (f + l).toUpperCase() || '?';
});

function scrollToSection(id) {
  activeSection.value = id;
  const el = document.getElementById('sec-' + id);
  el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function onScroll() {
  const ids = formSections.map(s => s.id);
  for (const id of [...ids].reverse()) {
    const el = document.getElementById('sec-' + id);
    if (el && el.getBoundingClientRect().top <= 140) {
      activeSection.value = id;
      break;
    }
  }
}

// ── Section completion ──────────────────────────────────────────────
function sectionDone(id) {
  if (id === 'account')    return !!(form.employee_id && form.role && (modal.editing || form.password));
  if (id === 'personal')   return !!(form.first_name && form.last_name);
  if (id === 'employment') return !!(form.staff_type);
  return false;
}
const completedSections = computed(() => formSections.filter(s => sectionDone(s.id)).length);

// ── Password strength ───────────────────────────────────────────────
const passwordStrength = computed(() => {
  const p = form.password;
  if (!p) return 0;
  let score = 0;
  if (p.length >= 8)              score++;
  if (/[A-Z]/.test(p))            score++;
  if (/[0-9]/.test(p))            score++;
  if (/[^A-Za-z0-9]/.test(p))    score++;
  return score;
});
const strengthBg   = computed(() => ['', 'bg-red-400', 'bg-amber-400', 'bg-blue-400', 'bg-emerald-500'][passwordStrength.value]);
const strengthText = computed(() => ['', 'text-red-500', 'text-amber-500', 'text-blue-500', 'text-emerald-600'][passwordStrength.value]);
const strengthLabel = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][passwordStrength.value]);

// ── Quick shift create ──────────────────────────────────────────────
const quickShift = reactive({
  open: false, saving: false, error: '',
  name: '', morning_in: '08:00', morning_out: '12:00',
  afternoon_in: '13:00', afternoon_out: '17:00',
  grace_period_minutes: 15, required_hours: 8,
});

function openQuickShift() {
  quickShift.name             = selectedStaffTypeLabel.value ? `${selectedStaffTypeLabel.value} Shift` : '';
  quickShift.morning_in       = '08:00'; quickShift.morning_out  = '12:00';
  quickShift.afternoon_in     = '13:00'; quickShift.afternoon_out = '17:00';
  quickShift.grace_period_minutes = 15;  quickShift.required_hours = 8;
  quickShift.error = ''; quickShift.open = true;
}

async function saveQuickShift() {
  quickShift.saving = true; quickShift.error = '';
  try {
    const res = await axios.post('/api/shifts', {
      name:                 quickShift.name,
      staff_type:           form.staff_type || null,
      morning_in:           quickShift.morning_in,
      morning_out:          quickShift.morning_out,
      afternoon_in:         quickShift.afternoon_in,
      afternoon_out:        quickShift.afternoon_out,
      grace_period_minutes: quickShift.grace_period_minutes,
      required_hours:       quickShift.required_hours,
    });
    shifts.value.push(res.data);
    form.shift_id   = res.data.id;
    quickShift.open = false;
    toast(`Shift "${res.data.name}" created.`, 'success');
  } catch (e) {
    quickShift.error = e.response?.data?.message
      ?? Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? 'Something went wrong.';
  } finally {
    quickShift.saving = false;
  }
}

// ── Smart shift grouping ────────────────────────────────────────────
const shiftGroups = computed(() => {
  if (!form.staff_type || !shifts.value.length) return null;
  return {
    recommended: shifts.value.filter(s => s.staff_type === form.staff_type),
    general:     shifts.value.filter(s => !s.staff_type),
    other:       shifts.value.filter(s => s.staff_type && s.staff_type !== form.staff_type),
  };
});

const selectedShift = computed(() =>
  form.shift_id ? (shifts.value.find(s => s.id == form.shift_id) ?? null) : null
);

const selectedStaffTypeLabel = computed(() =>
  staffTypes.find(s => s.value === form.staff_type)?.label ?? form.staff_type
);

const suggestedPositions = computed(() =>
  form.staff_type && positionsByType[form.staff_type]
    ? positionsByType[form.staff_type]
    : Object.values(positionsByType).flat()
);

watch(() => form.staff_type, (newType) => {
  if (!newType || form.shift_id) return;
  const rec = shifts.value.filter(s => s.staff_type === newType);
  if (rec.length === 1) form.shift_id = rec[0].id;
});

// ── Helpers ─────────────────────────────────────────────────────────
function initials(name) {
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function blankForm() {
  return {
    employee_id: '', first_name: '', last_name: '', middle_name: '', suffix: '',
    gender: '', civil_status: '', date_of_birth: '',
    email: '', password: '', role: 'employee',
    staff_type: '', position: '', employment_status: '',
    department_id: '', shift_id: '',
    contact_number: '', date_hired: '',
    gsis_number: '', philhealth_number: '', pagibig_number: '', tin_number: '',
    fingerprint_id: '',
  };
}

function onPhotoChange(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
  photoFile.value = file;
  photoPreview.value = URL.createObjectURL(file);
}

function openAdd() {
  Object.assign(form, blankForm());
  modal.editing = false; modal.editId = null;
  error.value = ''; showPassword.value = false;
  photoFile.value = null; photoPreview.value = null; editingEmp.value = null;
  activeSection.value = 'account';
  modal.open = true;
  unsavedGuard.snapshot(form);
}

function openEdit(emp) {
  Object.assign(form, {
    employee_id: emp.employee_id, first_name: emp.first_name, last_name: emp.last_name,
    middle_name: emp.middle_name ?? '', suffix: emp.suffix ?? '',
    gender: emp.gender ?? '', civil_status: emp.civil_status ?? '',
    date_of_birth: emp.date_of_birth ?? '', email: emp.email ?? '', password: '',
    role: emp.role, staff_type: emp.staff_type ?? '', position: emp.position ?? '',
    employment_status: emp.employment_status ?? '', department_id: emp.department_id ?? '',
    shift_id: emp.shift_id ?? '', contact_number: emp.contact_number ?? '',
    date_hired: emp.date_hired ?? '', gsis_number: emp.gsis_number ?? '',
    philhealth_number: emp.philhealth_number ?? '', pagibig_number: emp.pagibig_number ?? '',
    tin_number: emp.tin_number ?? '', fingerprint_id: emp.fingerprint_id ?? '',
  });
  modal.editing = true; modal.editId = emp.id;
  error.value = ''; showPassword.value = false;
  photoFile.value = null; photoPreview.value = null; editingEmp.value = emp;
  activeSection.value = 'account';
  modal.open = true;
  unsavedGuard.snapshot(form);
}

// Backdrop click / X / Cancel go through here — warns before discarding a
// dirty form. The post-save close in submit() skips this deliberately.
async function requestCloseModal() {
  await unsavedGuard.close(form, () => { modal.open = false; });
}

async function submit() {
  saving.value = true; error.value = '';
  try {
    const payload = { ...form };
    if (!payload.password)          delete payload.password;
    if (!payload.department_id)     payload.department_id = null;
    if (!payload.shift_id)          payload.shift_id = null;
    if (!payload.suffix)            payload.suffix = null;
    if (!payload.gender)            payload.gender = null;
    if (!payload.civil_status)      payload.civil_status = null;
    if (!payload.staff_type)        payload.staff_type = null;
    if (!payload.date_of_birth)     payload.date_of_birth = null;
    if (!payload.date_hired)        payload.date_hired = null;
    if (!payload.contact_number)    payload.contact_number = null;
    if (!payload.gsis_number)       payload.gsis_number = null;
    if (!payload.philhealth_number) payload.philhealth_number = null;
    if (!payload.pagibig_number)    payload.pagibig_number = null;
    if (!payload.tin_number)        payload.tin_number = null;
    if (!payload.fingerprint_id)    payload.fingerprint_id = null;

    if (modal.editing) {
      const res = await axios.put(`/api/employees/${modal.editId}`, payload);
      const idx = employees.value.findIndex(e => e.id === modal.editId);
      if (idx !== -1) employees.value[idx] = res.data;
      if (photoFile.value) {
        const fd = new FormData();
        fd.append('photo', photoFile.value);
        const pr = await axios.post(`/api/employees/${modal.editId}/photo`, fd);
        if (idx !== -1) employees.value[idx].photo_path = pr.data.photo_path;
      }
      toast('Employee updated.', 'success');
    } else {
      const res = await axios.post('/api/employees', payload);
      if (photoFile.value) {
        const fd = new FormData();
        fd.append('photo', photoFile.value);
        const pr = await axios.post(`/api/employees/${res.data.id}/photo`, fd);
        res.data.photo_path = pr.data.photo_path;
      }
      employees.value.unshift(res.data);
      toast('Employee added.', 'success');
    }
    modal.open = false;
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      ?? e.response?.data?.message ?? 'Something went wrong.';
  } finally {
    saving.value = false;
  }
}

async function deactivate(emp) {
  try {
    await axios.delete(`/api/employees/${emp.id}`);
    emp.is_active = false;
    toast(`${emp.name} deactivated.`, 'success', 6000, {
      actionLabel: 'Undo',
      onAction:    () => activate(emp),
    });
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to deactivate employee.', 'error');
  }
}

async function activate(emp) {
  try {
    const res = await axios.put(`/api/employees/${emp.id}`, { is_active: true });
    emp.is_active = res.data.is_active;
  } catch (e) {
    toast(e.response?.data?.message ?? 'Failed to activate employee.', 'error');
  }
}

async function load() {
  loading.value = true;
  try {
    const [empRes, deptRes, shiftRes] = await Promise.all([
      axios.get('/api/employees'),
      axios.get('/api/departments'),
      axios.get('/api/shifts'),
    ]);
    employees.value   = empRes.data;
    departments.value = deptRes.data;
    shifts.value      = shiftRes.data;
  } finally {
    loading.value = false;
  }
}

watch(() => modal.open, (open) => {
  if (!open && photoPreview.value) {
    URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = null;
    photoFile.value = null;
  }
});

onMounted(load);
</script>

<style scoped>
@reference "../css/app.css";

/* ── Filter bar ─────────────────────────────────────────── */
.select-sm {
  font-size: 13px; border: 1px solid #E4E9F2; border-radius: 8px;
  padding: 6px 12px; color: #0F172A; background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.select-sm:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }

/* ── Table ─────────────────────────────────────────────── */
.th-cell {
  padding: 11px 16px; font-size: 10.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #5A6899; background: #F7F8FD; white-space: nowrap;
}
.td-cell { padding: 12px 16px; color: #0F172A; }

/* ── Sortable column headers ────────────────────────────── */
.th-sort { cursor: pointer; user-select: none; transition: color 0.12s; }
.th-sort:hover { color: #0E1EC0; }

/* ── Table row hover ───────────────────────────────────── */
tbody tr { border-bottom: 1px solid #F1F5F9; transition: background 0.12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #F8FAFF; }

/* ── Action icons ──────────────────────────────────────── */
.btn-icon { padding: 6px; border-radius: 7px; color: #7B8FBF; transition: all 0.12s; }
.btn-icon:hover { background: #EEF2FF; color: #0E1EC0; }

/* ── Status badges ─────────────────────────────────────── */
.badge-green { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #ECFDF5; color: #059669; }
.badge-gray  { display: inline-flex; padding: 2px 8px; font-size: 11px; font-weight: 600; border-radius: 5px; background: #F1F5F9; color: #64748B; }

/* ── Avatar gradient ───────────────────────────────────── */
.emp-avatar {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  background: linear-gradient(135deg, #1228CC 0%, #0E1EC0 100%);
  display: flex; align-items: center; justify-content: center;
}

/* ── Modal form ────────────────────────────────────────── */
.form-label  { display: block; font-size: 13px; font-weight: 600; color: #1E293B; margin-bottom: 5px; }
.form-input  {
  width: 100%; padding: 8px 12px; font-size: 13px;
  border: 1px solid #E4E9F2; border-radius: 8px; color: #0F172A;
  background: #fff; transition: border-color 0.15s, box-shadow 0.15s;
}
.form-input:focus { outline: none; border-color: #0E1EC0; box-shadow: 0 0 0 3px rgba(14,30,192,0.08); }
.field-hint  { font-size: 11px; color: #9CABC8; margin-top: 4px; }
.req         { color: #EF4444; }
.btn-primary {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 18px; background: #0E1EC0; color: #fff;
  font-size: 13px; font-weight: 600; border-radius: 8px;
  border: none; cursor: pointer; transition: background 0.15s;
}
.btn-primary:hover:not(:disabled) { background: #1228CC; }
.btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-outline {
  padding: 8px 16px; font-size: 13px; font-weight: 500;
  border: 1px solid #E4E9F2; border-radius: 8px;
  color: #374151; background: #fff; cursor: pointer; transition: all 0.15s;
}
.btn-outline:hover { background: #F4F6FB; border-color: #C9D4E8; }

/* ── Table minimum width so columns don't squish ──────── */
.emp-table { min-width: 680px; }

/* ── Skeleton shimmer ──────────────────────────────────── */
.skel {
  background: linear-gradient(90deg, #F1F5F9 25%, #E8EDF5 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: emp-shimmer 1.4s infinite linear;
  border-radius: 4px;
}
.skel-avatar { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; }
.skel-lines  { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.skel-text   { height: 12px; }
@keyframes emp-shimmer { to { background-position: -200% 0; } }

/* ── Mobile ────────────────────────────────────────────── */
@media (max-width: 768px) {
  .px-6.py-3.bg-white { flex-wrap: wrap; gap: 8px; padding: 12px 16px; }
  .select-sm { width: 100%; }
  /* Modal: hide sidebar on tablets — not enough room */
  .modal-sidebar { display: none; }
}
@media (max-width: 640px) {
  .th-cell { padding: 9px 12px; }
  .td-cell { padding: 10px 12px; }
  .emp-avatar { width: 30px; height: 30px; border-radius: 7px; }
  /* Modal: full-screen on phones */
  .fixed.inset-0.z-50 > div:not(.absolute) {
    max-height: 100dvh; border-radius: 0; margin: 0;
  }
}
</style>
