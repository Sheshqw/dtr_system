<template>
  <div class="dash-page">

    <!-- ══ Campus identity header ══════════════════════════════════ -->
    <div class="dash-topbar" :style="{ background: campus.heroGradient }">

      <!-- Decorative dot-grid texture -->
      <div class="tb-deco-grid"></div>
      <!-- Decorative glow spot (campus color) -->
      <div class="tb-deco-glow" :style="{ background: campus.heroBand }"></div>

      <!-- ── Split row: Identity (left) | Clock + Controls (right) ── -->
      <div class="tb-split-row">

        <!-- Left: darker identity panel -->
        <div class="tb-identity">
          <div class="campus-seal">
            <img :src="settings.logoUrl" :alt="settings.systemName" class="seal-logo-img" />
            <div class="seal-ring"></div>
          </div>
          <div class="tb-brand">
            <div class="tb-brand-top">
              <span class="tb-wordmark">{{ settings.systemName }}</span>
              <span class="tb-brand-sep">·</span>
              <span class="tb-campus-name">{{ campusName }}</span>
            </div>
            <p class="tb-system">{{ settings.tagline }}</p>
          </div>
        </div>

        <!-- Right: live clock + controls -->
        <div class="tb-clock-zone">
          <div class="tb-clock-center">
            <span class="tb-center-date">{{ formattedDate }}</span>
            <div class="tb-center-clock">{{ liveTime }}</div>
            <span v-if="lastRefreshed" class="tb-center-updated">
              Updated {{ lastRefreshed }}
              <span v-if="liveUpdatesActive" class="tb-live-dot" title="Live — updates automatically when someone punches in/out"></span>
            </span>
          </div>
          <div class="tb-controls">
            <div
              v-if="deviceChecked"
              class="device-pill"
              :class="deviceOnline ? 'device-pill--online' : 'device-pill--offline'"
              :title="deviceOnline ? `Device online · Last synced: ${lastSynced ?? 'never'}` : 'Biometric device unreachable'"
            >
              <span class="device-dot"></span>
              <span>{{ deviceOnline ? 'Device Online' : 'Device Offline' }}</span>
            </div>
            <button @click="refresh" :disabled="store.loading" class="tb-refresh-btn">
              <RefreshCwIcon class="w-3.5 h-3.5" :class="store.loading ? 'animate-spin' : ''" />
              <span>Refresh</span>
            </button>
            <NotificationBell />
          </div>
        </div>
      </div>
    </div>

    <div class="dash-body">

      <!-- ══ Summary panel ═══════════════════════════════════════════ -->
      <div v-if="!ownRecordsOnly" class="summary-panel">

        <!-- ── Gradient hero header ─────────────────────────────── -->
        <div class="summary-hero" :style="{ background: campus.heroGradient }">
          <div class="sh-deco-grid"></div>
          <div class="sh-deco-glow" :style="{ background: campus.heroBand }"></div>
          <div class="sh-inner">
            <div class="sh-left">
              <div class="sh-icon-box">
                <BarChart2Icon class="w-4 h-4" style="color:rgba(255,255,255,0.9)" />
              </div>
              <div>
                <span class="summary-title">Today's Attendance Summary</span>
                <p class="summary-sub">{{ formattedDate }}</p>
              </div>
            </div>
            <div class="sh-tracked-pill">
              <span class="tracked-num">{{ totalTracked }}</span>
              <span class="tracked-label">employee{{ totalTracked !== 1 ? 's' : '' }} tracked</span>
            </div>
          </div>
        </div>

        <div class="summary-body">

          <!-- Three stat columns -->
          <div class="sc-group">
            <!-- Present -->
            <div
              class="sc-col sc-col--present"
              :class="{ 'sc-col--active': filters.status === 'present_incl_late' }"
              role="button" tabindex="0"
              title="Show only present/late employees in the table below"
              @click="toggleStatusFilter('present_incl_late')"
              @keyup.enter="toggleStatusFilter('present_incl_late')"
            >
              <div class="sc-top-accent" style="background:#16A34A;"></div>
              <div class="sc-inner">
                <div class="sc-icon-row">
                  <div class="sc-icon-box sc-icon--green">
                    <UserCheckIcon class="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <span class="sc-label">Present</span>
                    <p class="sc-sublabel">On time today</p>
                  </div>
                </div>
                <div class="sc-metric-row">
                  <span class="sc-num" style="color:#15803D;">{{ displayPresent }}</span>
                  <span class="sc-denom">/ {{ totalTracked }}</span>
                  <span v-if="statusDelta('present') !== 0" class="sc-delta" :class="`sc-delta--${deltaSentiment('present', statusDelta('present'))}`">
                    <component :is="statusDelta('present') > 0 ? TrendingUpIcon : TrendingDownIcon" class="w-3 h-3" />
                    {{ Math.abs(statusDelta('present')) }}
                  </span>
                </div>
                <div class="sc-bar-wrap">
                  <div class="sc-bar" style="background:linear-gradient(90deg,#16A34A,#15803D);" :style="{ width: pct(store.summary.present) + '%' }"></div>
                </div>
                <span class="sc-pct"><strong>{{ pct(store.summary.present) }}%</strong> attendance <span class="sc-vs">· vs prev. day</span></span>
              </div>
            </div>

            <div class="sc-divider"></div>

            <!-- Late -->
            <div
              class="sc-col sc-col--late"
              :class="{ 'sc-col--active': filters.status === 'late' }"
              role="button" tabindex="0"
              title="Show only late employees in the table below"
              @click="toggleStatusFilter('late')"
              @keyup.enter="toggleStatusFilter('late')"
            >
              <div class="sc-top-accent" style="background:#D97706;"></div>
              <div class="sc-inner">
                <div class="sc-icon-row">
                  <div class="sc-icon-box sc-icon--amber">
                    <ClockIcon class="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <span class="sc-label">Late</span>
                    <p class="sc-sublabel">Arrived after grace</p>
                  </div>
                </div>
                <div class="sc-metric-row">
                  <span class="sc-num" style="color:#B45309;">{{ displayLate }}</span>
                  <span class="sc-denom">/ {{ totalTracked }}</span>
                  <span v-if="statusDelta('late') !== 0" class="sc-delta" :class="`sc-delta--${deltaSentiment('late', statusDelta('late'))}`">
                    <component :is="statusDelta('late') > 0 ? TrendingUpIcon : TrendingDownIcon" class="w-3 h-3" />
                    {{ Math.abs(statusDelta('late')) }}
                  </span>
                </div>
                <div class="sc-bar-wrap">
                  <div class="sc-bar" style="background:linear-gradient(90deg,#F59E0B,#D97706);" :style="{ width: pct(store.summary.late) + '%' }"></div>
                </div>
                <span class="sc-pct"><strong>{{ pct(store.summary.late) }}%</strong> of total <span class="sc-vs">· vs prev. day</span></span>
              </div>
            </div>

            <div class="sc-divider"></div>

            <!-- Absent -->
            <div
              class="sc-col sc-col--absent"
              :class="{ 'sc-col--active': filters.status === 'absent' }"
              role="button" tabindex="0"
              title="Show only absent employees in the table below"
              @click="toggleStatusFilter('absent')"
              @keyup.enter="toggleStatusFilter('absent')"
            >
              <div class="sc-top-accent" style="background:#DC2626;"></div>
              <div class="sc-inner">
                <div class="sc-icon-row">
                  <div class="sc-icon-box sc-icon--red">
                    <UserXIcon class="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <span class="sc-label">Absent</span>
                    <p class="sc-sublabel">No record today</p>
                  </div>
                </div>
                <div class="sc-metric-row">
                  <span class="sc-num" style="color:#991B1B;">{{ displayAbsent }}</span>
                  <span class="sc-denom">/ {{ totalTracked }}</span>
                  <span v-if="statusDelta('absent') !== 0" class="sc-delta" :class="`sc-delta--${deltaSentiment('absent', statusDelta('absent'))}`">
                    <component :is="statusDelta('absent') > 0 ? TrendingUpIcon : TrendingDownIcon" class="w-3 h-3" />
                    {{ Math.abs(statusDelta('absent')) }}
                  </span>
                </div>
                <div class="sc-bar-wrap">
                  <div class="sc-bar" style="background:linear-gradient(90deg,#EF4444,#DC2626);" :style="{ width: pct(store.summary.absent) + '%' }"></div>
                </div>
                <span class="sc-pct"><strong>{{ pct(store.summary.absent) }}%</strong> of total <span class="sc-vs">· vs prev. day</span></span>
              </div>
            </div>
          </div>

          <div class="summary-vdiv"></div>

          <!-- Attendance rate -->
          <div class="rate-col">
            <div class="sc-icon-row">
              <div class="sc-icon-box sc-icon--navy">
                <TargetIcon class="w-4 h-4 text-white" />
              </div>
              <div>
                <span class="sc-label">Attendance Rate</span>
                <p class="sc-sublabel">Today's overall score</p>
              </div>
            </div>

            <div class="rate-main">
              <div class="rate-donut-wrap" :style="{ '--rate-glow': rateGlowColor }">
                <svg viewBox="0 0 100 100" class="rate-donut-svg" aria-hidden="true">
                  <!-- Track -->
                  <circle cx="50" cy="50" r="40" fill="none"
                    stroke="rgba(0,0,0,0.06)" stroke-width="10"/>
                  <!-- Arc -->
                  <circle cx="50" cy="50" r="40" fill="none"
                    :stroke="rateColor" stroke-width="10"
                    stroke-linecap="round"
                    stroke-dasharray="251.2"
                    :stroke-dashoffset="251.2 - (251.2 * attendanceRate / 100)"
                    transform="rotate(-90 50 50)"
                    class="rate-donut-arc"
                  />
                  <!-- Center value -->
                  <text x="50" y="46" text-anchor="middle"
                    font-size="19" font-weight="800"
                    :fill="rateColor"
                    font-family="system-ui,-apple-system,sans-serif"
                  >{{ attendanceRate }}%</text>
                  <!-- Center label -->
                  <text x="50" y="61" text-anchor="middle"
                    font-size="8.5" font-weight="600"
                    fill="#6B7A94"
                    font-family="system-ui,-apple-system,sans-serif"
                    letter-spacing="1"
                  >{{ attendanceRate >= 75 ? 'GOOD' : attendanceRate >= 50 ? 'FAIR' : 'LOW' }}</text>
                </svg>
              </div>
            </div>

            <!-- Stacked bar breakdown -->
            <div class="breakdown">
              <div class="bd-seg bd-present" :style="{ width: pct(store.summary.present) + '%' }" :title="`Present: ${store.summary.present ?? 0}`"></div>
              <div class="bd-seg bd-late"    :style="{ width: pct(store.summary.late) + '%' }"    :title="`Late: ${store.summary.late ?? 0}`"></div>
              <div class="bd-seg bd-absent"  :style="{ width: pct(store.summary.absent) + '%' }"  :title="`Absent: ${store.summary.absent ?? 0}`"></div>
              <div v-if="totalTracked === 0" class="bd-empty"></div>
            </div>

            <div class="bd-legend">
              <span class="bdl-item"><i class="bdl-dot" style="background:#16A34A;"></i>Present</span>
              <span class="bdl-item"><i class="bdl-dot" style="background:#D97706;"></i>Late</span>
              <span class="bdl-item"><i class="bdl-dot" style="background:#DC2626;"></i>Absent</span>
            </div>
          </div>

        </div>
      </div>

      <!-- ══ Pending print requests alert ════════════════════════════ -->
      <div
        v-if="isAdminOrHr && pendingPrintRequests > 0"
        class="dash-alert dash-alert--indigo"
        @click="router.push({ name: 'reports' })"
        role="button"
        tabindex="0"
        @keyup.enter="router.push({ name: 'reports' })"
      >
        <div class="ca-dot"></div>
        <span class="ca-text">
          <strong>{{ pendingPrintRequests }} DTR print {{ pendingPrintRequests === 1 ? 'request' : 'requests' }}</strong>
          awaiting your approval
        </span>
        <span class="ca-action">Review now →</span>
      </div>

      <!-- ══ My Personal DTR Status ═════════════════════════════════ -->
      <!-- Only for a Faculty / Staff account the Super Admin granted
           "view other employees"; everyone else limited to their own
           records gets the richer MyAttendanceDashboard below. -->
      <div v-if="!isAdminOrHr && !ownRecordsOnly" class="bg-white rounded-xl overflow-hidden" style="border:1px solid #E5E7EB;">
        <!-- Card header -->
        <div class="px-5 py-3.5 flex items-center gap-3" style="background:linear-gradient(135deg,#2E4A8C,#3D5CA3);border-radius:0.75rem 0.75rem 0 0;">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:rgba(255,255,255,0.15);">
            <UserCheckIcon class="w-4 h-4 text-white" />
          </div>
          <div>
            <p class="text-sm font-semibold text-white">My DTR Status</p>
            <p class="text-xs" style="color:rgba(255,255,255,0.6);">{{ formattedDate }}</p>
          </div>
          <div class="ml-auto">
            <StatusBadge :status="myTodayRow?.status ?? 'absent'" />
          </div>
        </div>

        <!-- Card body -->
        <div class="px-5 py-4 flex flex-wrap gap-4 items-center">
          <!-- Time In / Time Out -->
          <div class="flex items-center gap-6">
            <div class="text-center">
              <p class="text-[10px] font-semibold uppercase tracking-wider mb-1" style="color:#6B7A94;">Time In</p>
              <p class="text-base font-bold font-mono" :style="myTodayRow?.time_in ? 'color:#0F172A;' : 'color:#94A3B8;'">
                {{ myTodayRow?.time_in ?? '—' }}
              </p>
            </div>
            <div style="width:1px;height:32px;background:#F1F5F9;"></div>
            <div class="text-center">
              <p class="text-[10px] font-semibold uppercase tracking-wider mb-1" style="color:#6B7A94;">Time Out</p>
              <p class="text-base font-bold font-mono" :style="myTodayRow?.time_out ? 'color:#0F172A;' : 'color:#94A3B8;'">
                {{ myTodayRow?.time_out ?? '—' }}
              </p>
            </div>
            <div style="width:1px;height:32px;background:#F1F5F9;"></div>
            <div class="text-center">
              <p class="text-[10px] font-semibold uppercase tracking-wider mb-1" style="color:#6B7A94;">Hours</p>
              <p class="text-base font-bold" style="color:#0F172A;">
                {{ myTodayRow?.hours_rendered ? Number(myTodayRow.hours_rendered).toFixed(1) + 'h' : '—' }}
              </p>
            </div>
          </div>

          <!-- Divider -->
          <div class="flex-1"></div>

          <!-- Pending print request badge -->
          <div
            v-if="pendingPrintRequests > 0"
            class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors hover:opacity-80"
            style="background:#EEF2FF;border:1px solid #C7D2FE;"
            @click="router.push({ name: 'reports' })"
            title="View your print request status"
          >
            <FileTextIcon class="w-4 h-4 flex-shrink-0" style="color:#4338CA;" />
            <span class="text-sm font-semibold" style="color:#3730A3;">
              {{ pendingPrintRequests }} print request{{ pendingPrintRequests !== 1 ? 's' : '' }} pending
            </span>
            <span class="text-xs" style="color:#4338CA;">→ View</span>
          </div>

          <!-- Quick links -->
          <div class="flex items-center gap-2">
            <button
              @click="router.push({ name: 'reports' })"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
              style="background:#F0FDF4;color:#15803D;border:1px solid #BBF7D0;"
            >
              <FileTextIcon class="w-3.5 h-3.5" />
              My Form 48
            </button>
          </div>
        </div>
      </div>

      <!-- ══ Personal dashboard: today + insights (own records only) ═══ -->
      <MyAttendanceDashboard
        v-if="ownRecordsOnly"
        ref="insightsRef"
        :today-row="myTodayRow"
        :pending-print-requests="pendingPrintRequests"
        :hero-gradient="campus.heroGradient"
      />

      <!-- ══ Attendance log table ════════════════════════════════════ -->
      <!-- Campus-wide sections (this table + analytics) are hidden from anyone
           limited to their own records; the backend scopes/denies them too. -->
      <div v-if="!ownRecordsOnly" class="panel">

        <!-- Gradient title band -->
        <div class="panel-hero" :style="{ background: campus.heroGradient }">
          <div class="sh-deco-grid"></div>
          <div class="panel-hero-inner">
            <div class="panel-title-row">
              <div class="ph-icon-box">
                <UsersIcon class="w-4 h-4" style="color:rgba(255,255,255,0.85)" />
              </div>
              <div>
                <h2 class="panel-title" style="color:#fff;">Daily Attendance Log</h2>
                <p class="panel-sub" style="color:rgba(255,255,255,0.45);">{{ formattedDate }}</p>
              </div>
            </div>
            <span class="record-count-hero">
              {{ filteredRows.length }} record{{ filteredRows.length !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>

        <div class="panel-head">
          <div class="panel-filters">
            <input v-model="filters.date" type="date" class="f-ctrl" />
            <button
              @click="filters.date = todayStr; refresh()"
              class="f-btn"
              :class="{ 'f-btn--on': filters.date === todayStr }"
            >Today</button>
            <button
              @click="filters.date = yesterdayStr; refresh()"
              class="f-btn"
              :class="{ 'f-btn--on': filters.date === yesterdayStr }"
            >Yesterday</button>
            <div class="f-search-wrap">
              <SearchIcon class="f-search-icon" />
              <input
                v-model="filters.search"
                type="search"
                placeholder="Search name or ID…"
                class="f-ctrl f-search"
              />
              <button
                v-if="filters.search"
                @click="filters.search = ''"
                class="f-search-clear"
                title="Clear search"
                aria-label="Clear search"
              >
                <XIcon class="w-3 h-3" />
              </button>
            </div>
            <select v-model="filters.status" class="f-ctrl">
              <option value="">All Status</option>
              <option value="present_incl_late">Present (incl. Late)</option>
              <option value="present">Present (on-time only)</option>
              <option value="late">Late</option>
              <option value="absent">Absent</option>
              <option value="holiday">Holiday</option>
            </select>
            <button v-if="filtersActive" @click="clearFilters" class="f-clear-all">
              <XIcon class="w-3 h-3" /> Clear filters
            </button>
          </div>
        </div>

        <!-- Skeleton -->
        <template v-if="store.loading">
          <div v-for="i in 6" :key="i" class="skel-row">
            <div class="skel skel-avatar"></div>
            <div class="skel-lines">
              <div class="skel skel-text" style="width:140px;"></div>
              <div class="skel skel-text" style="width:85px;height:9px;"></div>
            </div>
            <div class="skel skel-text" style="width:100px;"></div>
            <div class="skel skel-text" style="width:72px;"></div>
            <div class="skel skel-text" style="width:72px;"></div>
            <div class="skel skel-text" style="width:58px;border-radius:12px;"></div>
            <div class="skel skel-text" style="width:40px;margin-left:auto;"></div>
          </div>
        </template>

        <div v-else class="table-scroll">
          <table class="log-table">
            <caption class="sr-only">Employee attendance records for {{ formattedDate }}</caption>
            <thead>
              <tr>
                <th class="th th-no">#</th>
                <th class="th th-l th-sort" @click="sort('name')">
                  <span class="th-sort-inner">Employee <SortIcon :state="sortState('name')" /></span>
                </th>
                <th class="th th-l th-sort th-dept" @click="sort('department')">
                  <span class="th-sort-inner">Department <SortIcon :state="sortState('department')" /></span>
                </th>
                <th class="th th-sort" @click="sort('time_in')">
                  <span class="th-sort-inner">Time In <SortIcon :state="sortState('time_in')" /></span>
                </th>
                <th class="th th-sort" @click="sort('time_out')">
                  <span class="th-sort-inner">Time Out <SortIcon :state="sortState('time_out')" /></span>
                </th>
                <th class="th th-sort" @click="sort('status')">
                  <span class="th-sort-inner">Status <SortIcon :state="sortState('status')" /></span>
                </th>
                <th class="th th-r th-sort" @click="sort('hours')">
                  <span class="th-sort-inner th-sort-inner--r">Hours <SortIcon :state="sortState('hours')" /></span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in sortedRows"
                :key="row.id"
                class="log-row row-enter"
                :class="[i % 2 === 1 ? 'log-row--alt' : '', `row-status--${row.status}`]"
                :style="{ animationDelay: i * 22 + 'ms' }"
              >
                <td class="td td-no">{{ i + 1 }}</td>
                <td class="td td-emp">
                  <div class="emp-wrap">
                    <div
                      class="emp-avatar"
                      :style="avatarStyle(row.name)"
                    >{{ initials(row.name) }}</div>
                    <div>
                      <div class="emp-name">{{ row.name }}</div>
                      <div class="emp-id">{{ row.employee_id }}</div>
                      <div class="emp-dept">{{ row.department }}</div>
                    </div>
                  </div>
                </td>
                <td class="td td-muted td-dept">{{ row.department }}</td>
                <td class="td td-mono td-in">{{ row.time_in ?? '—' }}</td>
                <td class="td td-mono td-out">{{ row.time_out ?? '—' }}</td>
                <td class="td td-status"><StatusBadge :status="row.status" :holiday-name="row.holiday_name" /></td>
                <td class="td td-r td-hours">
                  <span :class="row.hours_rendered > 0 ? 'hrs-val' : 'hrs-nil'">
                    {{ row.hours_rendered > 0 ? Number(row.hours_rendered).toFixed(1) + 'h' : '—' }}
                  </span>
                </td>
              </tr>

              <tr v-if="filteredRows.length === 0">
                <td colspan="7">
                  <div class="empty-state">
                    <div class="empty-icon-wrap" :style="{ background: campus.colorDim }">
                      <SearchIcon class="w-5 h-5" :style="{ color: campus.heroBand }" />
                    </div>
                    <p class="empty-title">No records found</p>
                    <p class="empty-sub">
                      No attendance records match your current filters for this date.
                    </p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══ Analytics section ══════════════════════════════════════ -->
      <div v-if="!ownRecordsOnly" class="analytics-card">

        <!-- Gradient hero header -->
        <div class="analytics-hero" :style="{ background: campus.heroGradient }">
          <div class="sh-deco-grid"></div>
          <div class="sh-deco-glow" :style="{ background: campus.heroBand }"></div>
          <div class="ah-inner">
            <div class="ah-left">
              <div class="sh-icon-box">
                <BarChart2Icon class="w-4 h-4" style="color:rgba(255,255,255,0.9)" />
              </div>
              <div>
                <h2 class="ah-title">Analytics Overview</h2>
                <p class="ah-sub">{{ trendDays }}-day attendance trends · department breakdown</p>
              </div>
            </div>
            <div class="ah-range-pills">
              <button
                v-for="d in [7, 14, 30, 90]"
                :key="d"
                class="range-pill"
                :class="{ 'range-pill--active': trendDays === d }"
                @click="trendDays = d"
              >{{ d }}D</button>
            </div>
          </div>
        </div>

        <!-- ── Top-level KPI cards ─────────────────────────────── -->
        <div class="top-kpis">

          <div class="tk-card">
            <div class="tk-icon tk-icon--blue">
              <ActivityIcon class="w-5 h-5" />
            </div>
            <div class="tk-body">
              <span class="tk-label">{{ trendDays }}-Day Avg Rate</span>
              <div class="tk-val-row">
                <span class="tk-val" :style="{ color: rateTierColor(trendAnalytics.avgNum) }">{{ displayAvgRate }}%</span>
                <span
                  v-if="trendAnalytics.delta !== 0"
                  class="tk-delta"
                  :class="trendAnalytics.delta > 0 ? 'tk-delta--up' : 'tk-delta--down'"
                  :title="`vs the first half of this ${trendDays}-day window`"
                >
                  <component :is="trendAnalytics.delta > 0 ? TrendingUpIcon : TrendingDownIcon" class="w-3 h-3" />
                  {{ Math.abs(trendAnalytics.delta) }}pt
                </span>
              </div>
            </div>
            <svg v-if="trendAnalytics.sparkline" class="tk-spark" viewBox="0 0 64 22" preserveAspectRatio="none" aria-hidden="true">
              <path :d="trendAnalytics.sparkline" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <circle v-if="trendAnalytics.sparkEnd" :cx="trendAnalytics.sparkEnd.x" :cy="trendAnalytics.sparkEnd.y" r="2.4" :fill="rateTierColor(trendAnalytics.avgNum)" />
            </svg>
          </div>

          <div class="tk-card">
            <div class="tk-icon tk-icon--green">
              <TrendingUpIcon class="w-5 h-5" />
            </div>
            <div class="tk-body">
              <span class="tk-label">Peak Day</span>
              <span class="tk-val tk-val--sm" style="color:#15803D;">{{ trendAnalytics.peak }}</span>
            </div>
            <span class="tk-chip tk-chip--green">Best</span>
          </div>

          <div class="tk-card">
            <div class="tk-icon tk-icon--red">
              <TrendingDownIcon class="w-5 h-5" />
            </div>
            <div class="tk-body">
              <span class="tk-label">Lowest Day</span>
              <span class="tk-val tk-val--sm" style="color:#B91C1C;">{{ trendAnalytics.lowest }}</span>
            </div>
            <span class="tk-chip tk-chip--red">Worst</span>
          </div>

          <div class="tk-card">
            <div class="tk-icon tk-icon--gold">
              <CalendarDaysIcon class="w-5 h-5" />
            </div>
            <div class="tk-body">
              <span class="tk-label">Active Days</span>
              <span class="tk-val">{{ displayActiveDays }}</span>
            </div>
            <span class="tk-chip tk-chip--gold">of {{ trendDays }}</span>
          </div>

          <div class="tk-card tk-card--ring">
            <div class="tk-ring" :style="{ background: todayMixGradient }">
              <div class="tk-ring-hole">
                <span class="tk-ring-total">{{ displayPresent + displayLate + displayAbsent }}</span>
              </div>
            </div>
            <div class="tk-body">
              <span class="tk-label">Today's Mix</span>
              <div class="tk-ring-legend">
                <span><i style="background:#15803D;"></i>{{ pct(store.summary.present) }}%</span>
                <span><i style="background:#D97706;"></i>{{ pct(store.summary.late) }}%</span>
                <span><i style="background:#DC2626;"></i>{{ pct(store.summary.absent) }}%</span>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Charts row ───────────────────────────────────────── -->
        <div class="charts-row">

          <!-- Trend chart -->
          <div class="chart-panel" :style="{ borderTopColor: campus.heroBand }">
            <div class="cp-header">
              <div class="cp-title-group">
                <span class="cp-badge" :style="{ background: campus.colorDim, color: campus.heroBand }">TREND</span>
                <div>
                  <h2 class="cp-title">{{ trendDays }}-Day Attendance Trend</h2>
                  <p class="cp-sub">Daily headcount — Present vs. Absent</p>
                </div>
              </div>
            </div>

            <!-- KPI strip -->
            <div class="chart-area">
              <canvas v-show="!analyticsLoading" ref="trendCanvas" role="img" :aria-label="`${trendDays}-day attendance trend`"></canvas>
              <div v-if="analyticsLoading" class="chart-skel">
                <div class="cs-bars">
                  <div v-for="i in 22" :key="i" class="an-sk cs-bar"
                    :style="{ height: (18 + Math.abs(Math.sin(i * 0.85) * 14 + i * 2.2)) + 'px' }">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Department breakdown -->
          <div class="chart-panel" style="border-top-color:#C9A227;">
            <div class="cp-header">
              <div class="cp-title-group">
                <span class="cp-badge cp-badge--gold">DEPT</span>
                <div>
                  <h2 class="cp-title">Department Breakdown</h2>
                  <p class="cp-sub">Today's attendance per department</p>
                </div>
              </div>
              <div class="dept-legend">
                <span class="dl-item"><i class="dl-dot dl-dot--present"></i>Present</span>
                <span class="dl-item"><i class="dl-dot dl-dot--absent"></i>Absent</span>
              </div>
            </div>

            <div class="chart-area chart-area--dept">
              <canvas v-show="!analyticsLoading && !deptEmpty" ref="deptCanvas" role="img" aria-label="Department attendance breakdown"></canvas>
              <div v-if="analyticsLoading" class="chart-skel chart-skel--dept">
                <div v-for="i in 5" :key="i" class="dept-skel-row">
                  <div class="an-sk" style="height:10px;width:110px;border-radius:4px;flex-shrink:0;"></div>
                  <div class="an-sk dept-skel-bar" :style="{ width: (35 + i * 12) + '%' }"></div>
                </div>
              </div>
              <div v-if="!analyticsLoading && deptEmpty" class="chart-empty">
                <BarChart2Icon class="w-8 h-8 mb-2 opacity-20" style="color:#6B7A94;" />
                <p>No department data for this date.</p>
              </div>
            </div>

            <!-- Department ranking table -->
            <div v-if="!analyticsLoading && deptRankings.length" class="dept-rank-list">
              <div class="drl-header">
                <span>Department</span>
                <span style="text-align:center;">Present</span>
                <span style="text-align:right;">Rate</span>
              </div>
              <div
                v-for="(d, i) in deptRankings"
                :key="d.name"
                class="drl-row"
                :class="{ 'drl-row--clickable': d.id }"
                role="button"
                :tabindex="d.id ? 0 : -1"
                :title="d.id ? `View ${d.name} employees` : d.name"
                @click="goToDepartment(d)"
                @keyup.enter="goToDepartment(d)"
              >
                <span class="drl-rank">{{ i + 1 }}</span>
                <span class="drl-name" :title="d.name">{{ d.name }}</span>
                <span class="drl-present">{{ d.present }}<span class="drl-total">/{{ d.total }}</span></span>
                <div class="drl-bar-wrap">
                  <div class="drl-bar"
                    :style="{ width: d.rate + '%', background: d.rate >= 75 ? 'linear-gradient(90deg, #4ADE80, #15803D)' : d.rate >= 50 ? 'linear-gradient(90deg, #FBBF24, #D97706)' : 'linear-gradient(90deg, #F87171, #DC2626)' }">
                  </div>
                </div>
                <span class="drl-rate"
                  :style="{ color: d.rate >= 75 ? '#15803D' : d.rate >= 50 ? '#D97706' : '#DC2626' }">
                  {{ d.rate }}%
                </span>
              </div>
            </div>

            <div class="dept-footer">
              <span>{{ store.summary.present ?? 0 }} present across all departments today</span>
            </div>
          </div>

          <!-- Staff type composition -->
          <div class="chart-panel chart-panel--wide" style="border-top-color:#2E4A8C;">
            <div class="cp-header">
              <div class="cp-title-group">
                <span class="cp-badge" style="background:#EEF2FF;color:#2E4A8C;">ROLE</span>
                <div>
                  <h2 class="cp-title">Staff Type Composition</h2>
                  <p class="cp-sub">Today's attendance by role — a second lens next to department</p>
                </div>
              </div>
              <div class="dept-legend">
                <span class="dl-item"><i class="dl-dot" style="background:#2E4A8C;"></i>Present</span>
                <span class="dl-item"><i class="dl-dot dl-dot--absent"></i>Absent</span>
              </div>
            </div>

            <div class="chart-area chart-area--staff">
              <canvas v-show="!analyticsLoading && !staffTypeEmpty" ref="staffTypeCanvas" role="img" aria-label="Attendance by staff type"></canvas>
              <div v-if="analyticsLoading" class="chart-skel chart-skel--dept">
                <div v-for="i in 4" :key="i" class="dept-skel-row">
                  <div class="an-sk" style="height:10px;width:110px;border-radius:4px;flex-shrink:0;"></div>
                  <div class="an-sk dept-skel-bar" :style="{ width: (35 + i * 12) + '%' }"></div>
                </div>
              </div>
              <div v-if="!analyticsLoading && staffTypeEmpty" class="chart-empty">
                <UsersIcon class="w-8 h-8 mb-2 opacity-20" style="color:#6B7A94;" />
                <p>No staff type data for this date. Set a staff type on employee profiles to see this breakdown.</p>
              </div>
            </div>
          </div>

          <!-- Campus comparison — Super Admin only, the one role that
               oversees more than a single campus and has anything to
               compare. Ranked list only (no separate chart canvas) — with
               just a handful of known campuses, a list already reads as
               clearly as a bar chart would, without a fourth chart on the
               page repeating the same present/absent shape a third time. -->
          <div v-if="isSuperAdmin" class="chart-panel chart-panel--wide" style="border-top-color:#7C3AED;">
            <div class="cp-header">
              <div class="cp-title-group">
                <span class="cp-badge" style="background:#F5F3FF;color:#7C3AED;">CAMPUS</span>
                <div>
                  <h2 class="cp-title">Campus Comparison</h2>
                  <p class="cp-sub">Today's attendance rate across every campus</p>
                </div>
              </div>
            </div>

            <div v-if="analyticsLoading" class="chart-skel chart-skel--dept" style="height:auto; padding:14px 8px;">
              <div v-for="i in 4" :key="i" class="dept-skel-row">
                <div class="an-sk" style="height:10px;width:110px;border-radius:4px;flex-shrink:0;"></div>
                <div class="an-sk dept-skel-bar" :style="{ width: (35 + i * 12) + '%' }"></div>
              </div>
            </div>

            <div v-else-if="!campusRankings.length" class="campus-empty">
              <BuildingIcon class="w-8 h-8 mb-2 opacity-20" style="color:#6B7A94;" />
              <p>No campus data for this date.</p>
            </div>

            <div v-else class="dept-rank-list">
              <div class="drl-header">
                <span>Campus</span>
                <span style="text-align:center;">Present</span>
                <span style="text-align:right;">Rate</span>
              </div>
              <div v-for="(c, i) in campusRankings" :key="c.name" class="drl-row">
                <span class="drl-rank">{{ i + 1 }}</span>
                <span class="drl-name" :title="c.name">{{ c.name }}</span>
                <span class="drl-present">{{ c.present }}<span class="drl-total">/{{ c.total }}</span></span>
                <div class="drl-bar-wrap">
                  <div class="drl-bar"
                    :style="{ width: c.rate + '%', background: c.rate >= 75 ? 'linear-gradient(90deg, #4ADE80, #15803D)' : c.rate >= 50 ? 'linear-gradient(90deg, #FBBF24, #D97706)' : 'linear-gradient(90deg, #F87171, #DC2626)' }">
                  </div>
                </div>
                <span class="drl-rate"
                  :style="{ color: c.rate >= 75 ? '#15803D' : c.rate >= 50 ? '#D97706' : '#DC2626' }">
                  {{ c.rate }}%
                </span>
              </div>
            </div>
          </div>

        </div>

      </div><!-- /analytics-card -->

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter }           from 'vue-router';
import { useAttendanceStore }  from '@/stores/attendance';
import { useAuthStore }        from '@/stores/auth';
import { usePermissionsStore } from '@/stores/permissions';
import { useSettingsStore }    from '@/stores/settings';
import { useThemeStore }       from '@/stores/theme';
import { useDebounce }         from '@/composables/useDebounce';
import { useCampusIdentity }   from '@/composables/useCampusIdentity';
import { useCountUp }          from '@/composables/useCountUp';
import { useAvatarColor }      from '@/composables/useAvatarColor';
import { useSortable }         from '@/composables/useSortable';
import SortIcon                from '@/components/SortIcon.vue';
import StatusBadge             from '@/components/StatusBadge.vue';
import NotificationBell        from '@/components/NotificationBell.vue';
import MyAttendanceDashboard   from '@/components/MyAttendanceDashboard.vue';
import axios from 'axios';
import { getEcho } from '@/echo';
import {
  RefreshCw    as RefreshCwIcon,
  Search       as SearchIcon,
  TrendingUp   as TrendingUpIcon,
  TrendingDown as TrendingDownIcon,
  Activity     as ActivityIcon,
  CalendarDays as CalendarDaysIcon,
  BarChart2    as BarChart2Icon,
  Users        as UsersIcon,
  Building2    as BuildingIcon,
  UserCheck    as UserCheckIcon,
  UserX          as UserXIcon,
  X              as XIcon,
  Target         as TargetIcon,
  Clock          as ClockIcon,
  FileText       as FileTextIcon,
} from 'lucide-vue-next';
import {
  Chart,
  LineController, BarController,
  CategoryScale, LinearScale,
  PointElement, LineElement, BarElement,
  Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(LineController, BarController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler);

const store  = useAttendanceStore();
const theme  = useThemeStore();
const auth   = useAuthStore();
const perms  = usePermissionsStore();
const settings = useSettingsStore();
const router = useRouter();
const { campus, campusCode, campusName } = useCampusIdentity();
const { avatarStyle } = useAvatarColor();

// ── Count-up animated display values (present/late/absent only) ─
const displayPresent = useCountUp(computed(() => store.summary.present ?? 0));
const displayLate    = useCountUp(computed(() => store.summary.late    ?? 0));
const displayAbsent  = useCountUp(computed(() => store.summary.absent  ?? 0));

// ── "Today's Mix" ring — present/late/absent as a lightweight CSS
// conic-gradient (no Chart.js canvas needed for a single static ring).
const todayMixGradient = computed(() => {
  const p = pct(store.summary.present ?? 0);
  const l = pct(store.summary.late ?? 0);
  const pEnd = p;
  const lEnd = p + l;
  return `conic-gradient(#15803D 0% ${pEnd}%, #D97706 ${pEnd}% ${lEnd}%, #DC2626 ${lEnd}% 100%)`;
});

const isAdminOrHr = computed(() =>
  ['super_admin', 'hr_officer'].includes(auth.employee?.role)
);
const isSuperAdmin = computed(() => auth.employee?.role === 'super_admin');
// Faculty / Staff by default — they get only their own "My DTR Status" card.
const ownRecordsOnly = computed(() => !perms.can('dtr_records.view_all'));
const insightsRef    = ref(null);

const myTodayRow = computed(() =>
  store.rows.find(r => r.employee_id === auth.employee?.employee_id) ?? null
);

// ── Live clock ──────────────────────────────────────────────────
const liveTime      = ref('');
const lastRefreshed = ref('');
let clockTimer      = null;

function tickClock() {
  liveTime.value = new Date().toLocaleTimeString('en-PH', {
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  });
}

// ── Filters ─────────────────────────────────────────────────────
// Local-calendar-day arithmetic, never routed through toISOString() — that
// converts to UTC, which silently shifts the date by a day for any local
// timezone ahead of UTC (e.g. PHT, UTC+8) during the hours before UTC has
// rolled over to the same calendar day yet.
function toLocalDateStr(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
function addDays(dateStr, days) {
  const d = new Date(dateStr + 'T00:00:00');
  d.setDate(d.getDate() + days);
  return toLocalDateStr(d);
}

const todayStr        = toLocalDateStr(new Date());
const yesterdayStr    = addDays(todayStr, -1);
const filters         = reactive({ date: todayStr, search: '', status: '' });
const debouncedSearch = useDebounce(computed(() => filters.search), 300);

const filtersActive = computed(() =>
  filters.date !== todayStr || filters.search !== '' || filters.status !== ''
);

// Clicking a summary card filters the table below to that status — clicking
// the already-active card clears it back to "All Status".
function toggleStatusFilter(status) {
  filters.status = filters.status === status ? '' : status;
}

function clearFilters() {
  filters.date   = todayStr;
  filters.search = '';
  filters.status = '';
  refresh();
}

const formattedDate = computed(() =>
  new Date(filters.date + 'T00:00:00').toLocaleDateString('en-PH', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  })
);

// ── Totals + rate ────────────────────────────────────────────────
const totalTracked   = computed(() =>
  (store.summary.present ?? 0) + (store.summary.late ?? 0) + (store.summary.absent ?? 0)
);

const attendanceRate = computed(() => {
  if (totalTracked.value === 0) return 0;
  return Math.round(((store.summary.present ?? 0) + (store.summary.late ?? 0)) / totalTracked.value * 100);
});

const rateColor = computed(() => {
  if (attendanceRate.value >= 75) return '#15803D';
  if (attendanceRate.value >= 50) return '#B45309';
  return '#B91C1C';
});
// Ambient glow behind the rate donut — same tier color as the ring itself,
// just softened to a translucent wash rather than a second hardcoded palette.
const rateGlowColor = computed(() => {
  const hex = rateColor.value.replace('#', '');
  const r = parseInt(hex.slice(0, 2), 16);
  const g = parseInt(hex.slice(2, 4), 16);
  const b = parseInt(hex.slice(4, 6), 16);
  return `rgba(${r},${g},${b},0.22)`;
});
function pct(v) {
  if (totalTracked.value === 0) return 0;
  return Math.round((v ?? 0) / totalTracked.value * 100);
}

// ── Filtered rows ────────────────────────────────────────────────
const filteredRows = computed(() => {
  let rows = store.rows;
  const s = debouncedSearch.value?.toLowerCase();
  if (s) rows = rows.filter(r =>
    r.name.toLowerCase().includes(s) || r.employee_id.toLowerCase().includes(s)
  );
  // The Present summary card's own count includes Late (matches the backend's
  // summary calculation), so its drill-down has to match both statuses too —
  // filtering to literal 'present' only would silently drop late employees
  // even though they're part of the number the user just clicked.
  if (filters.status === 'present_incl_late') {
    rows = rows.filter(r => r.status === 'present' || r.status === 'late');
  } else if (filters.status) {
    rows = rows.filter(r => r.status === filters.status);
  }
  return rows;
});

const STATUS_SORT_WEIGHT = { present: 0, late: 1, absent: 2, holiday: 3, weekend: 4, on_leave: 5 };
const { sortKey, sortDir, sort, sorted: sortedRows } = useSortable(filteredRows, {
  name:       (r) => r.name?.toLowerCase() ?? '',
  department: (r) => r.department?.toLowerCase() ?? '',
  time_in:    (r) => r.time_in ?? '',
  time_out:   (r) => r.time_out ?? '',
  status:     (r) => STATUS_SORT_WEIGHT[r.status] ?? 9,
  hours:      (r) => r.hours_rendered ?? 0,
});
function sortState(key) {
  return sortKey.value === key ? sortDir.value : null;
}

function initials(name) {
  return name?.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase() ?? '?';
}

// ── Pending DTR print requests ────────────────────────────────────
const pendingPrintRequests = ref(0);

async function fetchPendingPrintRequests() {
  try {
    const res = await axios.get('/api/dtr-print-consents');
    pendingPrintRequests.value = res.data?.summary?.pending ?? 0;
  } catch { /* ignore */ }
}

// ── Biometric device status ──────────────────────────────────────
const deviceOnline  = ref(false);
const deviceChecked = ref(false);
const lastSynced    = ref(null);

async function checkDeviceStatus() {
  try {
    const res = await axios.get('/api/biometric/status');
    deviceOnline.value  = res.data?.adms_online ?? false;
    lastSynced.value    = res.data?.adms_last_seen ?? null;
    deviceChecked.value = true;
  } catch (e) {
    // 403 = this role can't read device status (e.g. Faculty / Staff) —
    // hide the pill rather than claim the device is offline.
    deviceOnline.value  = false;
    deviceChecked.value = e.response?.status !== 403;
  }
}

// ── Refresh ──────────────────────────────────────────────────────
async function refresh() {
  store.date = filters.date;
  // 'present_incl_late' is a client-only sentinel (see filteredRows) — the
  // backend only knows the literal statuses, so send it unfiltered and let
  // the computed narrow it down locally.
  const backendStatus = filters.status === 'present_incl_late' ? '' : filters.status;
  const requests = [store.fetchToday({ search: filters.search, status: backendStatus })];
  // The day-over-day deltas and charts only feed the campus-wide sections.
  if (!ownRecordsOnly.value) requests.push(loadPreviousDaySummary(), loadDateAnalytics());
  // The insights card loads itself on mount — only later refreshes reload it.
  else if (insightsRef.value && lastRefreshed.value) requests.push(insightsRef.value.load());
  await Promise.all(requests);
  lastRefreshed.value = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

// ── Day-over-day comparison ────────────────────────────────────────
// A lightweight second call to the same endpoint for the day before the
// one currently in view — "vs previous day" rather than hardcoded
// "yesterday" so it still reads correctly while browsing the Yesterday
// filter or any picked date.
const previousDaySummary = ref({ present: 0, late: 0, absent: 0 });

async function loadPreviousDaySummary() {
  const prevDate = addDays(filters.date, -1);
  try {
    const res = await axios.get('/api/attendance/today', { params: { date: prevDate, summary_only: 1 } });
    previousDaySummary.value = res.data.summary;
  } catch {
    previousDaySummary.value = { present: 0, late: 0, absent: 0 };
  }
}

function statusDelta(metric) {
  return (store.summary[metric] ?? 0) - (previousDaySummary.value[metric] ?? 0);
}

// Arrow direction always follows the actual number; color follows whether
// that direction is good news for this particular metric — more "present"
// is good, but more "late"/"absent" is not.
function deltaSentiment(metric, delta) {
  if (delta === 0) return 'flat';
  const isIncrease  = delta > 0;
  const isFavorable = metric === 'present' ? isIncrease : !isIncrease;
  return isFavorable ? 'up' : 'down';
}

// ── Charts ───────────────────────────────────────────────────────
const trendDays        = ref(30);
const trendCanvas      = ref(null);
const deptCanvas       = ref(null);
const staffTypeCanvas  = ref(null);
const analyticsLoading = ref(false);
const deptEmpty        = ref(false);
const staffTypeEmpty   = ref(false);
let trendChart     = null;
let deptChart       = null;
let staffTypeChart  = null;
const trendData     = ref([]);
const deptData      = ref([]);
const staffTypeData = ref([]);
const campusData    = ref([]);

// Short display labels for staff_type keys — kept local rather than pulling
// in EmployeesView's full picker list, since only the label text is needed.
const STAFF_TYPE_LABELS = {
  faculty:        'Faculty / Instructor',
  administrative: 'Administrative',
  security:       'Security Guard',
  custodial:      'Custodial / Utility',
  maintenance:    'Maintenance',
  laboratory:     'Lab Technician',
  driver:         'Driver',
  other:          'Other',
};

watch(() => filters.date, debounce(refresh));
watch(trendDays, loadTrendAnalytics);
watch(() => theme.isDark, () => {
  buildTrendChart(trendData.value);
  buildDeptChart(deptData.value);
  buildStaffTypeChart(staffTypeData.value);
  // Rebuilding three charts back-to-back in the same tick (right as the whole
  // page's colors are also transitioning for the theme switch) is exactly the
  // kind of pile-up that leaves canvases stuck at display:none — see
  // unstickCanvases() below for the full explanation.
  unstickCanvases(
    [trendCanvas, deptCanvas, staffTypeCanvas],
    [trendChart, deptChart, staffTypeChart],
  );
});

const font = { family: 'system-ui, -apple-system, Arial, sans-serif', size: 11 };

const tc = {
  get gridColor()  { return theme.isDark ? 'rgba(255,255,255,0.06)' : 'rgba(226,232,240,0.65)'; },
  get tickColor()  { return theme.isDark ? '#475569' : '#6B7A94'; },
  get labelColor() { return theme.isDark ? '#94A3B8' : '#6B7280'; },
  get tooltipBg()  { return '#1A2444'; },
  get yTickColor() { return theme.isDark ? '#94A3B8' : '#374151'; },
};

// Muted sparkline path + an accent-colored endpoint dot — the trend line
// itself stays in the de-emphasis ink; only the current value carries color.
function buildSparkline(values) {
  const w = 64, h = 22, pad = 3;
  if (values.length < 2) return { path: '', end: null };

  const min   = Math.min(...values), max = Math.max(...values);
  const range = max - min || 1;
  const stepX = (w - pad * 2) / (values.length - 1);
  const toXY  = (v, i) => [pad + i * stepX, pad + (1 - (v - min) / range) * (h - pad * 2)];

  const path = values.map((v, i) => {
    const [x, y] = toXY(v, i);
    return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(' ');

  const [ex, ey] = toXY(values[values.length - 1], values.length - 1);
  return { path, end: { x: ex, y: ey } };
}

function rateTierColor(rate) {
  if (rate >= 75) return '#15803D';
  if (rate >= 50) return '#B45309';
  return '#B91C1C';
}

const trendAnalytics = computed(() => {
  const empty = { avg: '0%', avgNum: 0, peak: 'No data', lowest: 'No data', activeDays: 0, delta: 0, sparkline: '', sparkEnd: null };
  if (!trendData.value.length) return empty;

  const activeDays = trendData.value.filter(d => (d.present + d.absent) > 0);
  if (!activeDays.length) return empty;

  const rates  = activeDays.map(d => {
    const t = d.present + d.absent;
    return t > 0 ? Math.round(d.present / t * 100) : 0;
  });
  const avg    = Math.round(rates.reduce((a, b) => a + b, 0) / rates.length);
  const maxIdx = rates.indexOf(Math.max(...rates));
  const minIdx = rates.indexOf(Math.min(...rates));

  // Delta: second half of the window vs the first half — a lightweight
  // "improving or declining" signal without a second API call.
  let delta = 0;
  if (rates.length >= 4) {
    const mid        = Math.floor(rates.length / 2);
    const firstHalf   = rates.slice(0, mid);
    const secondHalf  = rates.slice(mid);
    const avgOf = arr => arr.reduce((a, b) => a + b, 0) / arr.length;
    delta = Math.round(avgOf(secondHalf) - avgOf(firstHalf));
  }

  const spark = buildSparkline(rates);

  return {
    avg:        avg + '%',
    avgNum:     avg,
    peak:       activeDays[maxIdx].label + ' (' + rates[maxIdx] + '%)',
    lowest:     activeDays[minIdx].label + ' (' + rates[minIdx] + '%)',
    activeDays: activeDays.length,
    delta,
    sparkline:  spark.path,
    sparkEnd:   spark.end,
  };
});

const displayAvgRate    = useCountUp(computed(() => trendAnalytics.value.avgNum));
const displayActiveDays = useCountUp(computed(() => trendAnalytics.value.activeDays));

const deptRankings = computed(() =>
  deptData.value
    .map(d => ({
      id:      d.id,
      name:    d.name || d.code || 'Unknown',
      present: d.present ?? 0,
      absent:  d.absent  ?? 0,
      total:   (d.present ?? 0) + (d.absent ?? 0),
      rate:    (d.present ?? 0) + (d.absent ?? 0) > 0
        ? Math.round((d.present ?? 0) / ((d.present ?? 0) + (d.absent ?? 0)) * 100)
        : 0,
    }))
    .sort((a, b) => b.rate - a.rate)
);

function goToDepartment(d) {
  if (!d.id) return;
  router.push({ name: 'employees', query: { department: String(d.id) } });
}

const campusRankings = computed(() =>
  campusData.value
    .map(c => ({
      name:    c.name || c.code || 'Unknown',
      present: c.present ?? 0,
      absent:  c.absent  ?? 0,
      total:   (c.present ?? 0) + (c.absent ?? 0),
      rate:    (c.present ?? 0) + (c.absent ?? 0) > 0
        ? Math.round((c.present ?? 0) / ((c.present ?? 0) + (c.absent ?? 0)) * 100)
        : 0,
    }))
    .sort((a, b) => b.rate - a.rate)
);

// Chart.js's own responsive-size detection briefly sets a canvas to
// display:none while it measures its parent, then restores it — but a chart
// getting destroyed and rebuilt (as every one of these charts does on each
// refetch) can leave that restore step stuck, permanently hiding the canvas.
// chart.resize() alone doesn't clear it; the stuck inline style has to be
// removed directly first. Centralized here since every rebuild path below
// needs the same fix-up, not just the theme toggle that originally surfaced it.
function unstickCanvases(canvasRefs, chartInstances) {
  requestAnimationFrame(() => {
    canvasRefs.forEach((c) => {
      if (c.value && c.value.style.display === 'none') c.value.style.display = '';
    });
    chartInstances.forEach((chart) => chart?.resize());
  });
}

// The 30/14/7/90-day trend is a fixed trailing window ending today — it only
// needs to change when the range pill changes, never when the user picks a
// different single day to inspect in the table above.
async function loadTrendAnalytics() {
  const res = await axios.get('/api/analytics/trend', { params: { days: trendDays.value } });
  trendData.value = res.data;
  buildTrendChart(trendData.value);
  unstickCanvases([trendCanvas], [trendChart]);
}

// Department / staff-type / campus breakdowns are all scoped to a single day
// (`date: filters.date`), so — unlike the trend above — these have to refetch
// whenever the viewed date changes, or they'll keep showing whatever date the
// page first loaded with even after the table above has moved on.
async function loadDateAnalytics() {
  const requests = [
    axios.get('/api/analytics/departments', { params: { date: filters.date } }),
    axios.get('/api/analytics/staff-types', { params: { date: filters.date } }),
  ];
  // Only Super Admin can even call this endpoint (403 for anyone else),
  // and only Super Admin has more than one campus to compare.
  if (isSuperAdmin.value) {
    requests.push(axios.get('/api/analytics/campus-comparison', { params: { date: filters.date } }));
  }
  const [deptRes, staffTypeRes, campusRes] = await Promise.all(requests);
  deptData.value      = deptRes.data;
  staffTypeData.value  = staffTypeRes.data;
  if (campusRes) campusData.value = campusRes.data;
  buildDeptChart(deptData.value);
  buildStaffTypeChart(staffTypeData.value);
  unstickCanvases([deptCanvas, staffTypeCanvas], [deptChart, staffTypeChart]);
}

// Direct end-value labels on the trend line — the value stays in neutral
// ink (identity is carried by the colored end-dot beside it, not by the
// text color), placed past the last point so it never overlaps the line.
const endLabelPlugin = {
  id: 'endLabels',
  afterDatasetsDraw(chart) {
    const { ctx } = chart;
    chart.data.datasets.forEach((dataset, i) => {
      const meta = chart.getDatasetMeta(i);
      if (meta.hidden) return;
      const lastPoint = meta.data[meta.data.length - 1];
      const lastValue = dataset.data[dataset.data.length - 1];
      if (!lastPoint || lastValue == null) return;

      ctx.save();
      ctx.font = '700 11px system-ui, -apple-system, sans-serif';
      ctx.fillStyle = tc.labelColor;
      ctx.textBaseline = 'middle';
      ctx.textAlign = 'left';
      ctx.fillText(String(lastValue), lastPoint.x + 8, lastPoint.y);
      ctx.restore();
    });
  },
};

function buildTrendChart(data) {
  if (trendChart) { trendChart.destroy(); trendChart = null; }
  if (!trendCanvas.value) return;

  const makeGradient = (ctx, chartArea, r, g, b) => {
    if (!chartArea) return `rgba(${r},${g},${b},0.1)`;
    const grad = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    grad.addColorStop(0,   `rgba(${r},${g},${b},0.28)`);
    grad.addColorStop(0.6, `rgba(${r},${g},${b},0.07)`);
    grad.addColorStop(1,   `rgba(${r},${g},${b},0)`);
    return grad;
  };

  // Emphasize only the last point of each line — it's the one carrying the
  // direct end-label, everything before it stays a thin, quiet marker.
  const endEmphasis = (base) => (ctx) =>
    ctx.dataIndex === ctx.dataset.data.length - 1 ? base + 2 : base;

  trendChart = new Chart(trendCanvas.value, {
    type: 'line',
    data: {
      labels: data.map(d => d.label),
      datasets: [
        {
          label: 'Present',
          data: data.map(d => d.present),
          borderColor: '#1A6E40',
          backgroundColor: ctx => makeGradient(ctx.chart.ctx, ctx.chart.chartArea, 26, 110, 64),
          fill: true, tension: 0.42,
          pointRadius: endEmphasis(2.5), pointHoverRadius: 5.5,
          pointBackgroundColor: '#1A6E40',
          pointBorderColor: '#fff', pointBorderWidth: 1.5,
          borderWidth: 2,
        },
        {
          label: 'Absent',
          data: data.map(d => d.absent),
          borderColor: '#DC2626',
          backgroundColor: ctx => makeGradient(ctx.chart.ctx, ctx.chart.chartArea, 220, 38, 38),
          fill: true, tension: 0.42,
          pointRadius: endEmphasis(2), pointHoverRadius: 4.5,
          pointBackgroundColor: '#DC2626',
          pointBorderColor: '#fff', pointBorderWidth: 1.5,
          borderWidth: 2,
          borderDash: [5, 3],
        },
      ],
    },
    plugins: [endLabelPlugin],
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 700, easing: 'easeOutQuart' },
      layout: { padding: { right: 30 } },
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 24, boxHeight: 2.5, font, color: tc.labelColor, padding: 22, usePointStyle: false },
        },
        tooltip: {
          bodyFont: font, titleFont: { ...font, weight: '600' },
          padding: 12, cornerRadius: 6,
          backgroundColor: '#1A2444',
          borderColor: 'rgba(201, 162, 39,0.45)', borderWidth: 1,
          callbacks: {
            title: items => items[0].label,
            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}`,
          },
        },
      },
      scales: {
        x: {
          ticks: { font: { ...font, size: 10 }, color: tc.tickColor, maxTicksLimit: 10, maxRotation: 0 },
          grid: { display: false }, border: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: { font, color: tc.tickColor, precision: 0, stepSize: 1 },
          grid: { color: tc.gridColor, drawTicks: false },
          border: { display: false },
        },
      },
    },
  });
}

// Left-to-right horizontal gradient for the indexAxis:'y' bar charts below —
// same scriptable-option shape as makeGradient() above, just oriented along
// chartArea.left→right instead of top→bottom.
function makeHGradient(ctx, chartArea, c1, c2) {
  if (!chartArea) return c2;
  const grad = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
  grad.addColorStop(0, c1);
  grad.addColorStop(1, c2);
  return grad;
}

function buildDeptChart(data) {
  if (deptChart) { deptChart.destroy(); deptChart = null; }
  deptEmpty.value = !data.length;
  if (!deptCanvas.value || !data.length) return;

  const labels = data.map(d => d.name || d.code);

  deptChart = new Chart(deptCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Present',
          data: data.map(d => d.present),
          // One fixed hue for every bar — department identity is already
          // carried by the y-axis label, not by color. A different hue per
          // row would just repaint the same "Present" series arbitrarily.
          backgroundColor: ctx => makeHGradient(ctx.chart.ctx, ctx.chart.chartArea, '#22C55E', '#15803D'),
          borderRadius: { topLeft: 0, topRight: 4, bottomLeft: 0, bottomRight: 4 },
          borderSkipped: 'left',
          barPercentage: 0.62, categoryPercentage: 0.88,
        },
        {
          label: 'Absent',
          data: data.map(d => d.absent),
          backgroundColor: 'rgba(226,232,240,0.85)',
          borderRadius: { topLeft: 0, topRight: 3, bottomLeft: 0, bottomRight: 3 },
          borderSkipped: 'left',
          barPercentage: 0.62, categoryPercentage: 0.88,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 600, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          bodyFont: font, titleFont: { ...font, weight: '600' },
          padding: 12, cornerRadius: 6,
          backgroundColor: '#1A2444',
          borderColor: 'rgba(201, 162, 39,0.45)', borderWidth: 1,
          callbacks: {
            title: items => data[items[0].dataIndex]?.name ?? labels[items[0].dataIndex],
            label: ctx => {
              const d = data[ctx.dataIndex];
              const total = (d.present ?? 0) + (d.absent ?? 0);
              const pct = total > 0 ? Math.round(ctx.parsed.x / total * 100) : 0;
              return ` ${ctx.dataset.label}: ${ctx.parsed.x}  (${pct}%)`;
            },
          },
        },
      },
      scales: {
        y: {
          grid: { display: false }, border: { display: false },
          ticks: {
            font: { ...font, size: 10.5 }, color: tc.yTickColor, padding: 6,
            // On a narrow mobile canvas, a long department name (e.g.
            // "Department of Information Technology and Computer Studies")
            // just gets silently clipped by the canvas instead of wrapping —
            // Chart.js doesn't wrap axis labels. Abbreviate only when the
            // canvas is actually narrow; the full name still shows in the
            // tooltip on tap/hover.
            callback(value) {
              const label = this.getLabelForValue(value);
              const maxLen = this.chart.width < 400 ? 16 : 999;
              return label.length > maxLen ? label.slice(0, maxLen - 1) + '…' : label;
            },
          },
        },
        x: {
          beginAtZero: true,
          ticks: { font, color: tc.tickColor, precision: 0, stepSize: 1 },
          grid: { color: tc.gridColor, drawTicks: false },
          border: { display: false },
        },
      },
    },
  });
}

// Same mark spec as buildDeptChart() — horizontal stacked bar, one fixed hue
// for "Present" (identity is already carried by the y-axis label), just a
// different hue than the department panel so the two are visually distinct
// at a glance while staying a single accent each, not a rainbow.
function buildStaffTypeChart(data) {
  if (staffTypeChart) { staffTypeChart.destroy(); staffTypeChart = null; }
  staffTypeEmpty.value = !data.length;
  if (!staffTypeCanvas.value || !data.length) return;

  const labels = data.map(d => STAFF_TYPE_LABELS[d.type] ?? d.type);

  staffTypeChart = new Chart(staffTypeCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Present',
          data: data.map(d => d.present),
          backgroundColor: ctx => makeHGradient(ctx.chart.ctx, ctx.chart.chartArea, '#3D5CA3', '#1B2A5E'),
          borderRadius: { topLeft: 0, topRight: 4, bottomLeft: 0, bottomRight: 4 },
          borderSkipped: 'left',
          barPercentage: 0.62, categoryPercentage: 0.78,
        },
        {
          label: 'Absent',
          data: data.map(d => d.absent),
          backgroundColor: 'rgba(226,232,240,0.85)',
          borderRadius: { topLeft: 0, topRight: 3, bottomLeft: 0, bottomRight: 3 },
          borderSkipped: 'left',
          barPercentage: 0.62, categoryPercentage: 0.78,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 600, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          bodyFont: font, titleFont: { ...font, weight: '600' },
          padding: 12, cornerRadius: 6,
          backgroundColor: '#1A2444',
          borderColor: 'rgba(201, 162, 39,0.45)', borderWidth: 1,
          callbacks: {
            title: items => labels[items[0].dataIndex],
            label: ctx => {
              const d = data[ctx.dataIndex];
              const total = (d.present ?? 0) + (d.absent ?? 0);
              const pct = total > 0 ? Math.round(ctx.parsed.x / total * 100) : 0;
              return ` ${ctx.dataset.label}: ${ctx.parsed.x}  (${pct}%)`;
            },
          },
        },
      },
      scales: {
        y: {
          grid: { display: false }, border: { display: false },
          ticks: { font: { ...font, size: 10.5 }, color: tc.yTickColor, padding: 6 },
        },
        x: {
          beginAtZero: true,
          ticks: { font, color: tc.tickColor, precision: 0, stepSize: 1 },
          grid: { color: tc.gridColor, drawTicks: false },
          border: { display: false },
        },
      },
    },
  });
}

// ── Live updates ─────────────────────────────────────────────────
// AppLayout already owns the lifecycle of the shared 'attendance' channel
// (it uses the same punches for the toast notification), so this only adds
// its own listener and removes only that listener on unmount — never
// `.leave()`s the channel itself, which would kill AppLayout's subscription
// for the rest of the app too.
let echoChannel = null;
// True only while the underlying WebSocket is actually connected — merely
// having a window.Echo *object* doesn't mean anything, since it's created
// unconditionally at app boot and stays truthy even if the Reverb server is
// unreachable (wrong host/IP, server down, network change). Previously this
// just checked `if (window.Echo)`, so the "Live" dot could show as active
// indefinitely while real-time updates were silently never arriving.
const liveUpdatesActive = ref(false);
let unbindConnectionState = null;
const debouncedLiveRefresh = debounce(refresh, 800);

function handleLivePunch() {
  // A punch landing right now always belongs to today — refreshing while
  // browsing a past date would silently swap the view out from under the
  // user, so only auto-refresh when today is actually on screen.
  if (filters.date === todayStr) debouncedLiveRefresh();
}

let dashboardUnmounted = false;

onBeforeUnmount(() => {
  dashboardUnmounted = true;
  clearInterval(clockTimer);
  trendChart?.destroy();
  deptChart?.destroy();
  staffTypeChart?.destroy();
  echoChannel?.stopListening('.punched', handleLivePunch);
  unbindConnectionState?.();
});

onMounted(async () => {
  tickClock();
  clockTimer = setInterval(tickClock, 1000);
  // refresh() already loads the date-scoped dept/staff/campus breakdowns —
  // only the trend (a separate, days-range-scoped query) needs to run
  // alongside it here, rather than fetching the date-scoped ones twice.
  analyticsLoading.value = true;
  try {
    await Promise.all([refresh(), ownRecordsOnly.value ? null : loadTrendAnalytics()]);
  } finally {
    analyticsLoading.value = false;
  }
  checkDeviceStatus();
  fetchPendingPrintRequests();

  const echo = await getEcho();
  if (echo && !dashboardUnmounted) {
    echoChannel = echo.channel('attendance');
    echoChannel.listen('.punched', handleLivePunch);

    const pusher = echo.connector?.pusher;
    if (pusher?.connection) {
      liveUpdatesActive.value = pusher.connection.state === 'connected';
      const onStateChange = (states) => { liveUpdatesActive.value = states.current === 'connected'; };
      pusher.connection.bind('state_change', onStateChange);
      unbindConnectionState = () => pusher.connection.unbind('state_change', onStateChange);
    }
  }
});
</script>

<style scoped>
/* ── Page ─────────────────────────────────────────────────────── */
.dash-page {
  min-height: 100%;
  background:
    radial-gradient(1000px 520px at 102% -10%, rgba(46, 74, 140,0.06), transparent 60%),
    radial-gradient(760px 460px at -8% 22%, rgba(201, 162, 39,0.05), transparent 55%),
    #F4F6FB;
  display: flex;
  flex-direction: column;
}

/* ── Campus identity header ───────────────────────────────────── */
.dash-topbar {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
  transition: background 0.35s ease;
  box-shadow: 0 4px 20px rgba(0,0,0,0.38);
}

/* Decorative dot-grid overlay */
.tb-deco-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,0.045) 1px, transparent 1px);
  background-size: 20px 20px;
  pointer-events: none;
  z-index: 0;
}

/* Decorative radial glow (campus accent, top-right) */
.tb-deco-glow {
  position: absolute;
  top: -80px;
  right: -80px;
  width: 300px;
  height: 300px;
  border-radius: 50%;
  opacity: 0.07;
  filter: blur(70px);
  pointer-events: none;
  z-index: 0;
  transition: background 0.35s ease;
}

/* ── Split row: Identity | Clock ──────────────────────────────── */
.tb-split-row {
  display: flex;
  align-items: stretch;
  position: relative;
  z-index: 1;
}
.tb-identity {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 24px;
  background: rgba(0,0,0,0.30);
  border-left: 3px solid rgba(201, 162, 39,0.60);
  border-right: 1px solid rgba(255,255,255,0.07);
  flex-shrink: 0;
  min-width: 300px;
}
.tb-clock-zone {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  gap: 16px;
}
.tb-clock-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  text-align: center;
}

/* Campus seal */
.campus-seal {
  width: 62px;
  height: 62px;
  border-radius: 50%;
  background: rgba(255,255,255,0.05);
  border: 1.5px solid rgba(201, 162, 39,0.50);
  box-shadow: 0 0 18px rgba(201, 162, 39,0.28), inset 0 0 12px rgba(201, 162, 39,0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}
.seal-logo-img {
  width: 44px;
  height: 44px;
  object-fit: contain;
  position: relative;
  z-index: 1;
  filter: drop-shadow(0 0 5px rgba(201, 162, 39,0.45)) brightness(1.05);
}
.seal-ring {
  position: absolute;
  inset: 4px;
  border-radius: 50%;
  border: 1px solid rgba(201, 162, 39,0.22);
  pointer-events: none;
}

/* Brand text */
.tb-brand { display: flex; flex-direction: column; gap: 4px; }
.tb-brand-top { display: flex; align-items: center; gap: 9px; }
.tb-wordmark {
  font-size: 18px;
  font-weight: 900;
  color: #fff;
  letter-spacing: 4px;
  line-height: 1;
  text-shadow: 0 0 24px rgba(255,255,255,0.25);
}
.tb-brand-sep { font-size: 13px; color: rgba(255,255,255,0.28); font-weight: 300; }
.tb-campus-name {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,0.70);
  line-height: 1;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.tb-system {
  font-size: 10.5px;
  font-weight: 600;
  color: #C9A227;
  letter-spacing: 0.55px;
  margin: 0;
  line-height: 1;
  text-shadow: 0 0 12px rgba(201, 162, 39,0.40);
  opacity: 0.92;
}
.tb-center-date {
  font-size: 11px;
  color: rgba(255,255,255,0.50);
  letter-spacing: 0.6px;
  text-transform: uppercase;
  font-weight: 500;
}
.tb-center-clock {
  font-size: 32px;
  font-weight: 700;
  color: #fff;
  font-family: 'Consolas', 'SF Mono', 'Fira Mono', monospace;
  letter-spacing: 3px;
  line-height: 1;
  text-shadow: 0 0 28px rgba(255,255,255,0.20);
  font-variant-numeric: tabular-nums;
}
.tb-center-updated {
  font-size: 9.5px;
  color: rgba(255,255,255,0.32);
  letter-spacing: 0.3px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tb-live-dot {
  display: inline-block;
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #4ADE80;
  box-shadow: 0 0 0 rgba(74,222,128,0.6);
  animation: liveDotPulse 2s ease-out infinite;
}
@keyframes liveDotPulse {
  0%   { box-shadow: 0 0 0 0 rgba(74,222,128,0.55); }
  70%  { box-shadow: 0 0 0 5px rgba(74,222,128,0); }
  100% { box-shadow: 0 0 0 0 rgba(74,222,128,0); }
}

/* Controls (right side of row 1) */
.tb-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

/* Device pill */
.device-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 13px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  border: 1px solid transparent;
  white-space: nowrap;
  cursor: default;
  transition: all 0.2s ease;
  backdrop-filter: blur(4px);
}
.device-pill--online  { background: rgba(21,128,61,0.2); border-color: rgba(74,222,128,0.3); color: #86efac; }
.device-pill--offline { background: rgba(239,68,68,0.18); border-color: rgba(248,113,113,0.3); color: #fca5a5; }
.device-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.device-pill--online  .device-dot { background: #4ade80; box-shadow: 0 0 0 2px rgba(74,222,128,0.3); animation: pulse-green 2s ease infinite; }
.device-pill--offline .device-dot { background: #f87171; }
@keyframes pulse-green {
  0%, 100% { box-shadow: 0 0 0 2px rgba(74,222,128,0.3); }
  50%       { box-shadow: 0 0 0 4px rgba(74,222,128,0.1); }
}

/* Refresh button */
.tb-refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  font-size: 11.5px;
  font-weight: 600;
  color: rgba(255,255,255,0.75);
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
  white-space: nowrap;
  backdrop-filter: blur(4px);
}
.tb-refresh-btn:hover    { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.28); }
.tb-refresh-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* ── Body ─────────────────────────────────────────────────────── */
.dash-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  flex: 1;
}

/* ── Shared pip accent bar ────────────────────────────────────── */
.panel-pip {
  width: 3px;
  height: 18px;
  border-radius: 2px;
  flex-shrink: 0;
  transition: background 0.35s ease;
}

/* ── Summary panel ────────────────────────────────────────────── */
.summary-panel {
  background: #fff;
  border: 1px solid #E4E9F2;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 32px -8px rgba(27, 42, 94,0.14), 0 1px 4px rgba(27, 42, 94,0.06);
}

/* Hero gradient header */
.summary-hero {
  position: relative;
  overflow: hidden;
  padding: 14px 22px;
  transition: background 0.35s ease;
}
.sh-deco-grid {
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
  background-size: 18px 18px;
  pointer-events: none;
}
.sh-deco-glow {
  position: absolute; top: -60px; right: -60px;
  width: 220px; height: 220px; border-radius: 50%;
  opacity: 0.1; filter: blur(50px);
  pointer-events: none; transition: background 0.35s ease;
}
.sh-inner {
  display: flex; align-items: center; justify-content: space-between;
  position: relative; z-index: 1;
}
.sh-left        { display: flex; align-items: center; gap: 12px; }
.sh-icon-box {
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  backdrop-filter: blur(4px);
}
.summary-title  { font-size: 13px; font-weight: 700; color: #fff; display: block; }
.summary-sub    { font-size: 10.5px; color: rgba(255,255,255,0.45); margin: 2px 0 0; }
.sh-tracked-pill {
  display: flex; align-items: baseline; gap: 4px;
  background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.18);
  padding: 5px 14px; border-radius: 20px;
}
.tracked-num    { font-size: 17px; font-weight: 800; color: #fff; line-height: 1; }
.tracked-label  { font-size: 11px; color: rgba(255,255,255,0.5); }

.summary-body {
  display: flex;
  align-items: stretch;
  min-height: 114px;
}

/* Three stat columns */
.sc-group {
  display: flex;
  flex: 1.8;
  align-items: stretch;
}
.sc-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  transition: background 0.2s, box-shadow 0.2s;
  cursor: pointer;
}
.sc-col:focus-visible { outline: 2px solid #2E4A8C; outline-offset: -2px; }
.sc-col--present { background: linear-gradient(180deg, rgba(21,128,61,0.04) 0%, rgba(21,128,61,0.01) 100%); }
.sc-col--late    { background: linear-gradient(180deg, rgba(217,119,6,0.04) 0%, rgba(217,119,6,0.01) 100%); }
.sc-col--absent  { background: linear-gradient(180deg, rgba(220,38,38,0.04) 0%, rgba(220,38,38,0.01) 100%); }

/* Active drill-down state — a card stays visibly "pressed" while its
   status filter is applied to the table below. */
.sc-col--active.sc-col--present { box-shadow: inset 0 0 0 2px rgba(21,128,61,0.45); }
.sc-col--active.sc-col--late    { box-shadow: inset 0 0 0 2px rgba(180,83,9,0.45); }
.sc-col--active.sc-col--absent  { box-shadow: inset 0 0 0 2px rgba(153,27,27,0.45); }

/* Day-over-day delta chip next to each big number */
.sc-delta {
  display: inline-flex; align-items: center; gap: 1px;
  font-size: 11px; font-weight: 700;
  padding: 1px 5px 1px 4px; border-radius: 999px;
  margin-left: 2px;
}
.sc-delta--up   { background: #F0FDF4; color: #15803D; }
.sc-delta--down { background: #FEF2F2; color: #B91C1C; }
.sc-vs { color: #94A3B8; font-weight: 500; }

/* Top accent line per column */
.sc-top-accent {
  height: 3px; width: 100%; flex-shrink: 0;
}

/* Stat content area */
.sc-inner {
  display: flex; flex-direction: column; gap: 8px;
  padding: 18px 22px 20px;
  flex: 1;
}

/* Icon + label row */
.sc-icon-row {
  display: flex; align-items: center; gap: 11px;
}
.sc-icon-box {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.sc-icon--green { background: linear-gradient(135deg, #16A34A, #15803D); box-shadow: 0 3px 10px rgba(21,128,61,0.30); }
.sc-icon--amber { background: linear-gradient(135deg, #F59E0B, #D97706); box-shadow: 0 3px 10px rgba(217,119,6,0.30); }
.sc-icon--red   { background: linear-gradient(135deg, #EF4444, #DC2626); box-shadow: 0 3px 10px rgba(220,38,38,0.30); }
.sc-icon--navy  { background: linear-gradient(135deg, #3D5CA3, #2E4A8C); box-shadow: 0 3px 10px rgba(46, 74, 140,0.30); }

.sc-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: #475569;
  display: block;
}
.sc-sublabel {
  font-size: 10px; color: #6B7A94; margin-top: 1px;
}

/* Metric number row: large value + denominator */
.sc-metric-row {
  display: flex; align-items: baseline; gap: 6px;
}
.sc-denom {
  font-size: 15px; font-weight: 500; color: #6B7A94;
}

.sc-num {
  font-size: 42px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}
.sc-bar-wrap {
  height: 7px;
  background: rgba(0,0,0,0.06);
  border-radius: 4px;
  overflow: hidden;
  width: 100%;
}
.sc-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.8s cubic-bezier(0.34,1.56,0.64,1);
  min-width: 0;
}
.sc-pct {
  font-size: 11px; color: #6B7A94;
}
.sc-pct strong { color: #475569; font-weight: 700; }

.sc-divider {
  width: 1px;
  background: linear-gradient(180deg, transparent, rgba(15,23,42,0.09) 22%, rgba(15,23,42,0.09) 78%, transparent);
  margin: 0;
  flex-shrink: 0;
}

/* Vertical rule */
.summary-vdiv {
  width: 1px;
  background: linear-gradient(180deg, transparent, rgba(27, 42, 94,0.12) 18%, rgba(27, 42, 94,0.12) 82%, transparent);
  flex-shrink: 0;
}

/* Rate column */
.rate-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 20px 24px;
  gap: 12px;
  background: #FAFBFE;
  border-left: 1px solid rgba(0,0,0,0.06);
}
.rate-main {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.rate-donut-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}
.rate-donut-svg  { width: 110px; height: 110px; }
.rate-donut-arc  { transition: stroke-dashoffset 1.4s cubic-bezier(0.34, 1.56, 0.64, 1); }

/* Breakdown bar */
.breakdown {
  display: flex;
  height: 6px;
  border-radius: 3px;
  overflow: hidden;
  background: #F4F6FB;
  gap: 1px;
  margin-top: 2px;
}
.bd-seg         { height: 100%; transition: width 0.65s ease; min-width: 0; }
.bd-present     { background: #15803D; }
.bd-late        { background: #D97706; }
.bd-absent      { background: #DC2626; }
.bd-empty       { flex: 1; background: #E2E8F0; }

.bd-legend {
  display: flex;
  align-items: center;
  gap: 14px;
}
.bdl-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
  color: #64748B;
}
.bdl-dot {
  display: inline-block;
  width: 8px; height: 8px;
  border-radius: 2px;
  flex-shrink: 0;
  font-style: normal;
}

/* ── Pending-requests alert bar ───────────────────────────────── */
.dash-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  background: #FEF9E7;
  border: 1px solid #FDE68A;
  border-left: 4px solid #D97706;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}
.dash-alert:hover { background: #FEF3C7; }
.ca-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #D97706;
  flex-shrink: 0;
  animation: pulse-amber 2s ease infinite;
}
@keyframes pulse-amber {
  0%, 100% { box-shadow: 0 0 0 0 rgba(217,119,6,0.4); }
  50%       { box-shadow: 0 0 0 5px rgba(217,119,6,0); }
}
.ca-text   { flex: 1; font-size: 13px; color: #92400E; }
.ca-action { font-size: 12px; font-weight: 600; color: #B45309; white-space: nowrap; }

/* ── Indigo variant — reuses the alert shape for a different alert kind ── */
.dash-alert--indigo {
  background: #EEF2FF;
  border-color: #C7D2FE;
  border-left-color: #4338CA;
}
.dash-alert--indigo:hover { background: #E0E7FF; }
.dash-alert--indigo .ca-dot {
  background: #4338CA;
  animation: pulse-indigo 2s ease infinite;
}
@keyframes pulse-indigo {
  0%, 100% { box-shadow: 0 0 0 0 rgba(67,56,202,0.4); }
  50%       { box-shadow: 0 0 0 5px rgba(67,56,202,0); }
}
.dash-alert--indigo .ca-text   { color: #3730A3; }
.dash-alert--indigo .ca-action { color: #4338CA; }

/* ── Panel (generic) ──────────────────────────────────────────── */
.panel {
  background: #fff;
  border: 1px solid #E4E9F2;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 32px -8px rgba(27, 42, 94,0.13), 0 1px 4px rgba(27, 42, 94,0.05);
}

/* Panel gradient hero header */
.panel-hero {
  position: relative; overflow: hidden;
  padding: 13px 20px;
  transition: background 0.35s ease;
}
.panel-hero-inner {
  position: relative; z-index: 1;
  display: flex; align-items: center; justify-content: space-between;
}
.panel-title-row {
  display: flex; align-items: center; gap: 10px; flex-shrink: 0;
}
.ph-icon-box {
  width: 32px; height: 32px; border-radius: 9px;
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.panel-title  { font-size: 13px; font-weight: 700; color: #fff; margin: 0; }
.panel-sub    { font-size: 11px; color: rgba(255,255,255,0.45); margin: 2px 0 0; }
.record-count-hero {
  font-size: 11px; font-weight: 600;
  color: rgba(255,255,255,0.55);
  background: rgba(255,255,255,0.10);
  border: 1px solid rgba(255,255,255,0.16);
  padding: 3px 10px; border-radius: 12px;
}

.panel-head {
  padding: 10px 20px;
  border-bottom: 1px solid #F4F6FB;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  background: #FAFBFD;
}

.record-count {
  font-size: 11px;
  font-weight: 600;
  color: #64748B;
  background: #F1F5F9;
  padding: 2px 9px;
  border-radius: 20px;
}

/* ── Filter controls ──────────────────────────────────────────── */
.panel-filters  { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.f-ctrl {
  font-size: 12px;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid #DDE4EE;
  color: #374151;
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}
.f-ctrl:focus { border-color: #2E4A8C; }
.f-btn {
  font-size: 12px;
  font-weight: 500;
  padding: 5px 12px;
  border-radius: 6px;
  border: 1px solid #DDE4EE;
  color: #374151;
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.f-btn:hover  { background: #F8FAFC; }
.f-btn--on    { background: #2A3C76; color: #fff; border-color: #2A3C76; }
.f-search-wrap { position: relative; }
.f-search-icon {
  position: absolute;
  left: 8px; top: 50%;
  transform: translateY(-50%);
  width: 13px; height: 13px;
  color: #6B7A94;
  pointer-events: none;
}
.f-search { padding-left: 28px; padding-right: 24px; width: 170px; }
.f-search-clear {
  position: absolute;
  right: 6px; top: 50%;
  transform: translateY(-50%);
  width: 16px; height: 16px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 4px;
  color: #6B7A94;
  transition: background 0.12s, color 0.12s;
}
.f-search-clear:hover { background: #F1F5F9; color: #475569; }

.f-clear-all {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 500;
  color: #64748B;
  padding: 5px 8px;
  border-radius: 6px;
  transition: background 0.12s, color 0.12s;
  white-space: nowrap;
}
.f-clear-all:hover { background: #FEF2F2; color: #DC2626; }

/* ── Attendance table ─────────────────────────────────────────── */
.table-scroll { overflow-x: auto; }
.log-table    { width: 100%; border-collapse: collapse; font-size: 13px; }

.th {
  padding: 10px 16px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #64748B;
  background: #F7F8FD;
  border-bottom: 1px solid #EEF2FA;
  text-align: center;
  white-space: nowrap;
}
.th-l  { text-align: left; }
.th-r  { text-align: right; }
.th-no { width: 40px; }

.th-sort { cursor: pointer; user-select: none; transition: color 0.12s; }
.th-sort:hover { color: #2E4A8C; }
.th-sort-inner { display: inline-flex; align-items: center; gap: 4px; }
.th-sort-inner--r { justify-content: flex-end; }

.log-row      { border-bottom: 1px solid #F1F5F9; transition: background 0.12s; }
.log-row:hover { background: #F4F7FD !important; }
.log-row--alt { background: #FAFBFE; }
.log-row:last-child { border-bottom: none; }

/* Status left-border indicator */
.row-status--present { box-shadow: inset 4px 0 0 #16A34A; }
.row-status--late    { box-shadow: inset 4px 0 0 #D97706; }
.row-status--absent  { box-shadow: inset 4px 0 0 #DC2626; }
.row-status--holiday { box-shadow: inset 4px 0 0 #6366F1; }

.td      { padding: 10px 16px; color: #374151; text-align: center; vertical-align: middle; }
.td-no   { font-size: 11px; color: #94A3B8; font-family: monospace; }
.td-muted { text-align: left; font-size: 12px; color: #64748B; white-space: nowrap; }
.td-mono { font-family: 'Courier New', monospace; font-size: 12px; color: #475569; }
.td-r    { text-align: right; }
.td-emp  { text-align: left; }
.emp-dept { display: none; font-size: 11px; color: #64748B; margin-top: 2px; }

.emp-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}
.emp-avatar {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 900;
  flex-shrink: 0;
  letter-spacing: 0.4px;
  color: #fff;
  transition: background 0.35s ease;
}
.emp-name { font-size: 13px; font-weight: 600; color: #0F172A; }
.emp-id   { font-size: 10px; color: #6B7A94; font-family: monospace; margin-top: 1px; }
.hrs-val  { font-weight: 600; color: #0F172A; font-family: monospace; }
.hrs-nil  { color: #94A3B8; }

/* ── Empty state ──────────────────────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 52px 24px;
}
.empty-icon-wrap {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
  transition: background 0.35s ease;
}
.empty-title { font-size: 13px; font-weight: 700; color: #475569; margin: 0; }
.empty-sub   { font-size: 12px; color: #6B7A94; margin: 0; text-align: center; max-width: 280px; line-height: 1.5; }

/* ── Analytics card (wrapper) ─────────────────────────────────── */
.analytics-card {
  background: #fff;
  border: 1px solid #E4E9F2;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 32px -8px rgba(27, 42, 94,0.13), 0 1px 4px rgba(27, 42, 94,0.05);
}

/* Analytics gradient hero header */
.analytics-hero {
  position: relative; overflow: hidden;
  padding: 14px 22px;
  transition: background 0.35s ease;
}
.ah-inner {
  display: flex; align-items: center; justify-content: space-between;
  position: relative; z-index: 1;
}
.ah-left   { display: flex; align-items: center; gap: 12px; }
.ah-title  { font-size: 13px; font-weight: 700; color: #fff; margin: 0; }
.ah-sub    { font-size: 10.5px; color: rgba(255,255,255,0.45); margin: 2px 0 0; }
.ah-range-pills {
  display: flex; align-items: center; gap: 4px;
  background: rgba(0,0,0,0.20); border: 1px solid rgba(255,255,255,0.12);
  border-radius: 22px; padding: 3px;
}
.range-pill {
  font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.55);
  background: transparent; border: none; cursor: pointer;
  padding: 3px 11px; border-radius: 18px;
  transition: background 0.18s, color 0.18s;
  white-space: nowrap;
}
.range-pill:hover { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); }
.range-pill--active {
  background: rgba(255,255,255,0.20);
  color: #fff;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.30), 0 3px 8px rgba(0,0,0,0.18);
}

/* ── Top KPI cards ────────────────────────────────────────────── */
.top-kpis {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0;
  border-bottom: 1px solid #F1F5F9;
}
@media (max-width: 1200px) {
  .top-kpis { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
  .top-kpis { grid-template-columns: repeat(2, 1fr); }
}
.tk-card {
  background: #fff;
  border-right: 1px solid #F1F5F9;
  border-radius: 0;
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
  overflow: hidden;
  transition: background 0.15s, transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s ease;
}
.tk-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(46, 74, 140,0.45), transparent);
  opacity: 0;
  transition: opacity 0.25s ease;
}
.tk-card:hover {
  background: #FAFBFF;
  transform: translateY(-3px);
  box-shadow: 0 14px 28px -12px rgba(27, 42, 94,0.22);
  z-index: 1;
}
.tk-card:hover::before { opacity: 1; }
.tk-card:last-child { border-right: none; }
.tk-icon {
  width: 46px; height: 46px;
  border-radius: 13px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  color: #fff;
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
.tk-card:hover .tk-icon { transform: scale(1.08) rotate(-3deg); }
.tk-card:hover .tk-ring { transform: scale(1.06); }
.tk-icon--blue  { background: linear-gradient(135deg, #2E4A8C, #3D5CA3); box-shadow: 0 3px 10px rgba(46, 74, 140,0.30); }
.tk-icon--green { background: linear-gradient(135deg, #16A34A, #15803D); box-shadow: 0 3px 10px rgba(21,128,61,0.30); }
.tk-icon--red   { background: linear-gradient(135deg, #EF4444, #DC2626); box-shadow: 0 3px 10px rgba(220,38,38,0.30); }
.tk-icon--gold  { background: linear-gradient(135deg, #F59E0B, #D97706); box-shadow: 0 3px 10px rgba(217,119,6,0.30); }
.tk-body {
  display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 0;
}
.tk-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.09em; color: #6B7A94; }
.tk-val   { font-size: 28px; font-weight: 800; color: #0F172A; line-height: 1.1; }
.tk-val--sm { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.tk-val-row { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.tk-delta {
  display: inline-flex; align-items: center; gap: 2px;
  font-size: 10.5px; font-weight: 700; padding: 2px 6px 2px 5px;
  border-radius: 999px; flex-shrink: 0;
}
.tk-delta--up   { background: #F0FDF4; color: #15803D; }
.tk-delta--down { background: #FEF2F2; color: #B91C1C; }
.tk-spark { width: 64px; height: 22px; flex-shrink: 0; }
.tk-chip  { font-size: 9.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; align-self: flex-start; }
.tk-chip--blue  { background: #EEF2FF; color: #1D4ED8; }
.tk-chip--green { background: #F0FFF4; color: #15803D; }
.tk-chip--red   { background: #FEF2F2; color: #B91C1C; }
.tk-chip--gold  { background: #FFFBEB; color: #B45309; }

/* ── "Today's Mix" ring KPI ──────────────────────────────────────── */
.tk-ring {
  width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.5s ease, transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
.tk-ring-hole {
  width: 36px; height: 36px; border-radius: 50%;
  background: #fff;
  display: flex; align-items: center; justify-content: center;
}
.tk-ring-total { font-size: 12px; font-weight: 800; color: #0F172A; }
.tk-ring-legend { display: flex; gap: 8px; flex-wrap: wrap; }
.tk-ring-legend span { display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 700; color: #374151; }
.tk-ring-legend i { display: inline-block; width: 6px; height: 6px; border-radius: 50%; }

/* ── Charts ───────────────────────────────────────────────────── */
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 16px 20px 20px; }
/* Staff type panel spans the full row, below the trend + department pair —
   a 3rd item in a 2-col grid would otherwise strand itself in one column. */
.chart-panel--wide { grid-column: 1 / -1; }

.chart-panel {
  background: #fff;
  border: 1px solid #EEF2FA;
  border-top: 3px solid #C9A227;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: border-top-color 0.35s ease;
}

.cp-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #F4F6FB;
  gap: 12px;
  flex-wrap: wrap;
  background: #FAFBFD;
}
.cp-title-group { display: flex; align-items: center; gap: 12px; }
.cp-badge {
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 4px 9px;
  border-radius: 6px;
  flex-shrink: 0;
  transition: background 0.35s ease, color 0.35s ease;
}
.cp-badge--gold { background: #FEF9E7; color: #92400E; }
.cp-title { font-size: 13px; font-weight: 700; color: #0F172A; margin: 0; }
.cp-sub   { font-size: 11px; color: #6B7A94; margin: 2px 0 0; }

/* ── KPI strip ────────────────────────────────────────────────── */
.kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  border-bottom: 1px solid #EEF2FA;
  background: #F7F8FD;
}
.kpi-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-right: 1px solid #ECF0F8;
}
.kpi-card--last { border-right: none; }
.kpi-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon--blue  { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; }
.kpi-icon--green { background: linear-gradient(135deg, #16A34A, #15803D); color: #fff; }
.kpi-icon--red   { background: linear-gradient(135deg, #EF4444, #DC2626); color: #fff; }
.kpi-icon--gold  { background: linear-gradient(135deg, #F59E0B, #D97706); color: #fff; }
.kpi-body  { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.kpi-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: #6B7A94; }
.kpi-val   { font-size: 12px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-val--green { color: #166534; }
.kpi-val--red   { color: #B91C1C; }

.chart-area       { padding: 6px 18px 18px; position: relative; height: 215px; }
.chart-area--dept { height: 265px; }
.chart-area--staff { height: 190px; }

/* Dept legend */
.dept-legend { display: flex; align-items: center; gap: 12px; }
.dl-item     { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #64748B; }
.dl-dot      { display: inline-block; width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; font-style: normal; }
.dl-dot--present { background: #1A5F9E; }
.dl-dot--absent  { background: #E2E8F0; }

/* Dept footer */
.dept-footer {
  padding: 9px 18px;
  border-top: 1px solid #F4F6FB;
  font-size: 10.5px;
  color: #6B7A94;
  background: #FAFBFD;
}

/* Chart empty state */
.chart-empty {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  font-size: 12px; color: #6B7A94;
}

/* Campus comparison empty state — not inside a positioned .chart-area
   (there's no canvas here), so it's a plain centered block instead of
   the absolute-inset one above. */
.campus-empty {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 34px 20px;
  font-size: 12px; color: #6B7A94;
}

.chart-spinner-wrap { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
.chart-spinner {
  width: 20px; height: 20px;
  border-radius: 50%;
  border: 2px solid #F4F6FB;
  border-top-color: #C9A227;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Analytics skeletons ──────────────────────────────────────── */
@keyframes an-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.an-sk {
  background: linear-gradient(90deg, #F4F6FB 25%, #DDE3F0 50%, #F4F6FB 75%);
  background-size: 200% 100%;
  animation: an-shimmer 1.5s ease-in-out infinite;
  border-radius: 4px;
}
.an-sk--icon { width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0; }

/* Trend chart skeleton bars */
.chart-skel {
  width: 100%; height: 100%;
  display: flex; flex-direction: column; justify-content: flex-end; gap: 0;
  padding: 0 4px;
  position: relative;
}
.cs-bars {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 100%;
  padding-bottom: 12px;
}
.cs-bar {
  flex: 1;
  min-height: 8px;
  border-radius: 3px 3px 0 0;
}

/* Dept skeleton rows */
.chart-skel--dept {
  display: flex; flex-direction: column; justify-content: center; gap: 16px;
  padding: 12px 8px;
  height: 100%;
}
.dept-skel-row {
  display: flex; align-items: center; gap: 12px;
}
.dept-skel-bar {
  height: 18px; border-radius: 0 4px 4px 0; flex-shrink: 0;
}

/* ── Department ranking list ──────────────────────────────────── */
.dept-rank-list {
  border-top: 1px solid #F4F6FB;
  padding: 10px 16px;
  background: #FAFBFD;
}
.drl-header {
  display: grid;
  grid-template-columns: 1fr auto auto;
  padding: 0 22px 6px 22px;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: #6B7A94;
  gap: 8px;
}
.drl-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px solid #F1F5F9;
}
.drl-row:last-child { border-bottom: none; }
.drl-row--clickable {
  cursor: pointer;
  border-radius: 6px;
  margin: 0 -6px;
  padding-left: 6px;
  padding-right: 6px;
  transition: background 0.12s;
}
.drl-row--clickable:hover,
.drl-row--clickable:focus-visible { background: #F4F6FB; }
.drl-rank    { font-size: 9.5px; font-weight: 700; color: #94A3B8; width: 14px; flex-shrink: 0; text-align: right; }
.drl-name    { font-size: 10.5px; font-weight: 500; color: #374151; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.drl-present { font-size: 10.5px; font-weight: 700; color: #0F172A; width: 32px; text-align: center; flex-shrink: 0; }
.drl-total   { font-size: 9px; color: #6B7A94; font-weight: 400; }
.drl-bar-wrap { flex: 1; height: 5px; background: #F4F6FB; border-radius: 3px; overflow: hidden; }
.drl-bar      { height: 100%; border-radius: 3px; transition: width 0.6s ease; }
.drl-rate     { font-size: 10.5px; font-weight: 700; width: 34px; text-align: right; flex-shrink: 0; }

/* ── Skeleton ─────────────────────────────────────────────────── */
.skel-row {
  display: flex; align-items: center; gap: 14px;
  padding: 11px 16px; border-bottom: 1px solid #F9FAFB;
}
.skel {
  background: linear-gradient(90deg, #F1F5F9 25%, #E8EDF5 50%, #F1F5F9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 4px;
}
.skel-avatar  { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; }
.skel-lines   { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.skel-text    { height: 12px; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* ── Responsive ───────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .charts-row { grid-template-columns: 1fr; }
  .top-kpis   { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 860px) {
  .summary-body  { flex-direction: column; }
  .summary-vdiv  { width: 100%; height: 1px; margin: 0; }
  .sc-group      { border-bottom: 1px solid #ECF0F8; }
}

/* ── Panels that adapt to their own width ─────────────────────────────
   Media queries only know the screen size, but with the sidebar open a
   1100px laptop leaves these panels about as narrow as a tablet. Container
   queries key the layout to the space each panel actually has. */
.summary-panel { container-type: inline-size; }
.table-scroll  { container-type: inline-size; }

@container (max-width: 900px) {
  .summary-body { flex-direction: column; }
  .summary-vdiv { width: 100%; height: 1px; margin: 0; }
  .sc-group     { border-bottom: 1px solid #ECF0F8; }
}

/* Present / Late / Absent: too narrow for three columns, so each becomes
   a full-width row — label left, number right, bar and percentage under. */
@container (max-width: 600px) {
  .sc-group   { flex-direction: column; }
  .sc-divider { width: 100%; height: 1px; background: rgba(15,23,42,0.07); }
  .sc-inner   {
    display: grid; grid-template-columns: 1fr auto; align-items: center;
    column-gap: 12px; row-gap: 8px; padding: 12px 14px;
  }
  .sc-bar-wrap, .sc-pct { grid-column: 1 / -1; }
}

/* Log table: drop the row number and fold the department under the name,
   so Status and Hours stay in view instead of scrolling off the right. */
@container (max-width: 899px) {
  .th-no, .td-no, .th-dept, .td-dept { display: none; }
  .emp-dept { display: block; }
}

/* Phones: each log row becomes a compact card — name and status on top,
   In / Out / Hours underneath — so nothing needs sideways scrolling. */
@container (max-width: 520px) {
  .log-table, .log-table tbody { display: block; }
  .log-table thead { display: none; }
  .log-table .log-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    align-items: center;
    padding: 10px 14px;
    row-gap: 8px;
  }
  .log-table .log-row .td { padding: 0; text-align: left; }
  .log-table .td-emp    { grid-column: 1 / 4; }
  .log-table .td-status { grid-column: 4; justify-self: end; }
  .log-table .td-in, .log-table .td-out, .log-table .td-hours {
    grid-row: 2; display: flex; flex-direction: column; gap: 1px;
  }
  .log-table .td-in    { grid-column: 1; }
  .log-table .td-out   { grid-column: 2; }
  .log-table .td-hours { grid-column: 3; }
  .log-table .td-in::before, .log-table .td-out::before, .log-table .td-hours::before {
    font-family: var(--font-sans);
    font-size: 9.5px; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; color: #94A3B8;
  }
  .log-table .td-in::before    { content: 'In'; }
  .log-table .td-out::before   { content: 'Out'; }
  .log-table .td-hours::before { content: 'Hours'; }
}

/* ── Header: identity above the clock whenever the content area is narrow
   (tablets, and small laptops where the sidebar takes 256px) — side by
   side it squeezed the clock onto two lines and pushed the bell off. ── */
.tb-center-clock, .tb-center-date { white-space: nowrap; }
.tb-clock-zone { flex-wrap: wrap; }
@media (max-width: 1279px) {
  .tb-split-row  { flex-wrap: wrap; }
  .tb-identity   { min-width: 100%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.07); }
  .tb-clock-zone { min-width: 100%; }
}

/* ── Tablet (≤768px) ──────────────────────────────────────────── */
@media (max-width: 768px) {
  .tb-center-clock { font-size: 36px; letter-spacing: 4px; }
  .dash-body     { padding: 14px 16px; }
  .top-kpis      { grid-template-columns: repeat(2, 1fr); }
  .panel-head    { flex-wrap: wrap; gap: 8px; }
  .panel-filters { flex-wrap: wrap; }
}

/* ── Phone (≤640px) ───────────────────────────────────────────── */
@media (max-width: 640px) {
  /* Topbar */
  .tb-identity   { padding: 12px 14px; }
  .tb-clock-zone { padding: 10px 14px; }
  .tb-center-clock { font-size: 30px; letter-spacing: 3px; }
  .tb-center-date  { font-size: 10px; }
  .tb-center-updated { font-size: 9px; }
  .tb-controls   { width: 100%; justify-content: space-between; }
  .tb-wordmark   { font-size: 14px; letter-spacing: 2px; }
  .tb-campus-name { font-size: 11px; }
  .campus-seal   { display: none; }
  .tb-brand-sep  { display: none; }

  /* Body */
  .dash-body     { padding: 10px 12px; gap: 10px; }
  .top-kpis      { grid-template-columns: 1fr 1fr; }
  .tk-card       { padding: 14px 12px; border-bottom: 1px solid #F1F5F9; }
  .tk-val        { font-size: 20px; }
  .tk-label      { font-size: 9px; }
  .tk-icon       { width: 36px; height: 36px; border-radius: 10px; }
  .charts-row    { grid-template-columns: 1fr; padding: 10px 12px 14px; gap: 10px; }

  /* The clock takes its own line so the device pill, Refresh and the bell
     get the full width under it instead of being pushed off the edge. */
  .tb-clock-zone   { flex-wrap: wrap; gap: 10px; }
  .tb-clock-center { flex: 1 1 100%; }
  .tb-center-clock { font-size: 28px; letter-spacing: 2px; }

  /* Summary panel */
  .summary-hero    { padding: 10px 14px; }
  .rate-col        { padding: 12px 14px; gap: 8px; }
  .sc-num          { font-size: 28px; }
  .rate-donut-svg  { width: 80px; height: 80px; }
  .kpi-strip       { grid-template-columns: repeat(2, 1fr); }
  .kpi-card--last  { border-right: none; }
  .kpi-card:nth-child(2) { border-right: none; }

  /* Log table */
  .table-scroll     { overflow-x: auto; }
  .log-table        { min-width: 0; }
  .log-table .th, .log-table .td { padding-left: 8px; padding-right: 8px; }
  .td-mono          { font-size: 11px; }
  .panel-head       { flex-direction: column; align-items: flex-start; gap: 8px; }
  .panel-filters    { width: 100%; }
  /* Search and status each take a full row so they're easy to tap. */
  .f-search-wrap, .panel-filters > select.f-ctrl { flex: 1 1 100%; }
  .f-search         { width: 100%; }
  .panel-head-right { width: 100%; justify-content: flex-end; }

  /* Charts */
  .charts-row   { gap: 10px; }
  .chart-panel  { padding: 14px; }
  .cp-title     { font-size: 12px; }

  /* Alert bar */
  .dash-alert { flex-direction: column; gap: 6px; padding: 10px 12px; }
}

/* ═══════════════════════════════════════════════════════════════
   MICRO-DETAILS — high-impact finishing touches
   ═══════════════════════════════════════════════════════════════ */

/* ── 1. Table row stagger entrance (keyframe was missing) ─────── */
@keyframes rowEnter {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: translateY(0); }
}
.row-enter {
  animation: rowEnter 0.28s ease forwards;
  opacity: 0;
}

/* ── 2. Gradient clip text on the big stat numbers ────────────── */
.sc-col--present .sc-num {
  background: linear-gradient(170deg, #15803D 10%, #4ADE80 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.sc-col--late .sc-num {
  background: linear-gradient(170deg, #B45309 10%, #FCD34D 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.sc-col--absent .sc-num {
  background: linear-gradient(170deg, #991B1B 10%, #F87171 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ── 3. Hero header moving sheen sweep ────────────────────────── */
.summary-hero::before,
.panel-hero::before,
.analytics-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    105deg,
    transparent 30%,
    rgba(255,255,255,0.05) 50%,
    transparent 70%
  );
  /* Moved with transform, not background-position: a transform runs on
     the GPU, while animating background-position repainted the whole
     header every frame for as long as the page stayed open. */
  transform: translateX(-100%);
  animation: heroSheen 7s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}
@keyframes heroSheen {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* ── 4. Stat column colored inset glow on hover ───────────────── */
.sc-col { transition: background 0.2s, box-shadow 0.22s; }
.sc-col--present:hover { box-shadow: inset 0 0 40px rgba(21,128,61,0.08), inset 3px 0 0 rgba(21,128,61,0.30); }
.sc-col--late:hover    { box-shadow: inset 0 0 40px rgba(180,83,9,0.08),  inset 3px 0 0 rgba(180,83,9,0.30); }
.sc-col--absent:hover  { box-shadow: inset 0 0 40px rgba(153,27,27,0.08), inset 3px 0 0 rgba(153,27,27,0.30); }

/* ── 5. Donut ring elevation ──────────────────────────────────── */
.rate-donut-svg { filter: drop-shadow(0 2px 12px rgba(0,0,0,0.13)); }

/* ── 6. Chart panel hover shadow ─────────────────────────────── */
.chart-panel { transition: border-top-color 0.35s ease, box-shadow 0.25s ease, transform 0.25s ease; }
.chart-panel:hover { box-shadow: 0 16px 32px -14px rgba(27, 42, 94,0.20); transform: translateY(-2px); }

/* ── 7. Icon boxes lift subtly with their parent card ─────────── */
.sc-icon-box { transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1); }
.sc-col:hover .sc-icon-box { transform: scale(1.08) rotate(-3deg); }

/* ── 8. Table row avatar responds to row hover ────────────────── */
.emp-avatar { transition: background 0.35s ease, transform 0.2s ease; }
.log-row:hover .emp-avatar { transform: scale(1.06); }

/* ── 9. Pending-requests banner lifts on hover ─────────────────── */
.dash-alert { transition: background 0.15s, transform 0.18s ease, box-shadow 0.18s ease; }
.dash-alert:hover { transform: translateY(-1px); box-shadow: 0 6px 18px -6px rgba(67,56,202,0.28); }

/* ── 10. Department ranking rows nudge right on hover ─────────── */
.drl-row--clickable { transition: background 0.15s ease, transform 0.15s ease; }
.drl-row--clickable:hover { transform: translateX(2px); }

/* ── 11. Ambient glow behind the attendance-rate donut ────────── */
.rate-donut-wrap { position: relative; }
.rate-donut-wrap::before {
  content: '';
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--rate-glow, rgba(21,128,61,0.20)) 0%, transparent 72%);
  filter: blur(7px);
  z-index: 0;
  animation: rateGlowPulse 3.2s ease-in-out infinite;
}
.rate-donut-svg { position: relative; z-index: 1; }
@keyframes rateGlowPulse {
  0%, 100% { opacity: 0.5; }
  50%      { opacity: 1; }
}
</style>
