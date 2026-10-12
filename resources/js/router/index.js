import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';
import { usePermissionsStore } from '@/stores/permissions';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { title: 'Dashboard' } },
            { path: 'attendance',    name: 'attendance',    component: () => import('@/views/AttendanceView.vue'),    meta: { title: 'Attendance' } },
            { path: 'employees',     name: 'employees',     component: () => import('@/views/EmployeesView.vue'),     meta: { title: 'Employee Logs', requiresFeature: 'dtr_records.view_all' } },
            { path: 'tracking',      name: 'tracking',      component: () => import('@/views/TrackingView.vue'),      meta: { title: 'Tracking', requiresFeature: 'tracking_analytics' } },
            { path: 'reports',       name: 'reports',       component: () => import('@/views/ReportsView.vue'),       meta: { title: 'Reports' } },
            { path: 'holidays',      name: 'holidays',      component: () => import('@/views/HolidayView.vue'),       meta: { title: 'Holidays', requiresFeature: 'holiday_calendar' } },
            { path: 'shifts',        name: 'shifts',        component: () => import('@/views/ShiftsView.vue'),        meta: { title: 'Shifts', requiresFeature: 'shift_management' } },
            { path: 'departments',   name: 'departments',   component: () => import('@/views/DepartmentsView.vue'),   meta: { title: 'Departments' } },
            { path: 'campuses',      name: 'campuses',      component: () => import('@/views/CampusView.vue'),        meta: { title: 'Campuses' } },
            { path: 'admin-accounts',name: 'admin-accounts',component: () => import('@/views/AdminAccountsView.vue'), meta: { title: 'Admin Accounts' } },
            { path: 'backup',        name: 'backup',        component: () => import('@/views/BackupView.vue'),        meta: { title: 'Backup' } },
            { path: 'audit',         name: 'audit',         component: () => import('@/views/AuditView.vue'),         meta: { title: 'Audit Trail' } },
            { path: 'system-health', name: 'system-health', component: () => import('@/views/SystemHealthView.vue'), meta: { title: 'System Health', requiresFeature: 'system_health' } },
            { path: 'access-control', name: 'access-control', component: () => import('@/views/AccessControlView.vue'), meta: { title: 'Access Control' } },
            { path: 'settings',      name: 'settings',      component: () => import('@/views/SystemSettingsView.vue'), meta: { title: 'System Settings', roles: ['super_admin'] } },
            { path: 'biometric',     name: 'biometric',     component: () => import('@/views/BiometricView.vue'),     meta: { title: 'Biometric' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Page not found' } },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.token) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.token) {
        return { name: 'dashboard' };
    }

    // Pages limited to specific roles (the API enforces this too; this just
    // keeps a typed-in URL from opening a page that would only show errors).
    if (to.meta.roles && !to.meta.roles.includes(auth.employee?.role)) {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresFeature) {
        const permissions = usePermissionsStore();
        if (!permissions.can(to.meta.requiresFeature)) {
            return { name: 'dashboard' };
        }
    }
});

router.afterEach((to) => {
    const title = to.meta?.title;
    const settings = useSettingsStore();
    const brand = `${settings.systemName} ${settings.shortName}`;
    document.title = title ? `${title} — ${brand}` : brand;

    // Reaching here means the navigation actually succeeded — the app is
    // healthy again, so a *future* stale-chunk error (from a later rebuild)
    // should be free to trigger another auto-reload. See onError below.
    sessionStorage.removeItem('chunk-reload-pending');
});

// Warm up every page's code in the background once the user is signed in and
// the browser is idle, so the first visit to each page opens instantly instead
// of pausing on a network fetch. One file at a time, so it never competes with
// the page the user is actually looking at; failures are ignored (the normal
// navigation path below still loads — and self-heals — on demand).
let pagesPrefetched = false;
router.afterEach((to) => {
    if (pagesPrefetched || to.name === 'login') return;
    pagesPrefetched = true;

    const whenIdle = window.requestIdleCallback ?? ((cb) => setTimeout(cb, 1500));
    whenIdle(async () => {
        for (const route of router.getRoutes()) {
            const load = route.components?.default;
            if (typeof load !== 'function' || route.name === 'login') continue;
            try { await load(); } catch { /* loaded on demand instead */ }
        }
    });
});

// Every page is lazy-loaded (`() => import(...)`), so its JS file is only
// fetched the first time a browser tab actually visits it. If the app gets
// rebuilt/redeployed while someone already has it open — the filenames
// change every build — that fetch 404s and the navigation silently leaves
// the page blank. A hard reload picks up the current build's file list, so
// do that automatically instead of leaving the user staring at nothing.
//
// The guard is a single session-wide flag, not per-route, and afterEach
// above clears it on every successful navigation. That distinguishes "a
// fresh rebuild just made this route stale again" (should still auto-heal —
// this can legitimately happen many times over a long-lived tab) from "we
// just reloaded and hit the exact same error again with no successful
// navigation in between" (a real loop, stop retrying).
router.onError((error, to) => {
    const isStaleChunk = /Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed/i.test(error.message);
    if (!isStaleChunk) return;

    if (sessionStorage.getItem('chunk-reload-pending')) return; // reloaded and failed again immediately — don't loop
    sessionStorage.setItem('chunk-reload-pending', '1');
    window.location.href = to.fullPath;
});

export default router;
