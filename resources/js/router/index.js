import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore } from '@/stores/settings';

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
            { path: 'employees',     name: 'employees',     component: () => import('@/views/EmployeesView.vue'),     meta: { title: 'Employees' } },
            { path: 'tracking',      name: 'tracking',      component: () => import('@/views/TrackingView.vue'),      meta: { title: 'Tracking' } },
            { path: 'reports',       name: 'reports',       component: () => import('@/views/ReportsView.vue'),       meta: { title: 'Reports' } },
            { path: 'holidays',      name: 'holidays',      component: () => import('@/views/HolidayView.vue'),       meta: { title: 'Holidays' } },
            { path: 'shifts',        name: 'shifts',        component: () => import('@/views/ShiftsView.vue'),        meta: { title: 'Shifts' } },
            { path: 'departments',   name: 'departments',   component: () => import('@/views/DepartmentsView.vue'),   meta: { title: 'Departments' } },
            { path: 'campuses',      name: 'campuses',      component: () => import('@/views/CampusView.vue'),        meta: { title: 'Campuses' } },
            { path: 'admin-accounts',name: 'admin-accounts',component: () => import('@/views/AdminAccountsView.vue'), meta: { title: 'Admin Accounts' } },
            { path: 'corrections',   name: 'corrections',   component: () => import('@/views/DtrCorrectionView.vue'), meta: { title: 'DTR Corrections' } },
            { path: 'backup',        name: 'backup',        component: () => import('@/views/BackupView.vue'),        meta: { title: 'Backup' } },
            { path: 'audit',         name: 'audit',         component: () => import('@/views/AuditView.vue'),         meta: { title: 'Audit Trail' } },
            { path: 'system-health', name: 'system-health', component: () => import('@/views/SystemHealthView.vue'), meta: { title: 'System Health' } },
            { path: 'settings',      name: 'settings',      component: () => import('@/views/SystemSettingsView.vue'), meta: { title: 'System Settings' } },
            { path: 'biometric',     name: 'biometric',     component: () => import('@/views/BiometricView.vue'),     meta: { title: 'Biometric' } },
            { path: ':pathMatch(.*)*', name: 'not-found', redirect: { name: 'dashboard' } },
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
});

router.afterEach((to) => {
    const title = to.meta?.title;
    const settings = useSettingsStore();
    const brand = `${settings.systemName} ${settings.shortName}`;
    document.title = title ? `${title} — ${brand}` : brand;
});

export default router;
