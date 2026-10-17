import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useReferenceDataStore } from '@/stores/referenceData';

export const useAuthStore = defineStore('auth', () => {
    const token    = ref(localStorage.getItem('dtr_token') || sessionStorage.getItem('dtr_token') || null);

    function resolveEmployee() {
        const raw = localStorage.getItem('dtr_employee') || sessionStorage.getItem('dtr_employee');
        if (!raw) return null;
        try { return JSON.parse(raw); } catch {
            localStorage.removeItem('dtr_employee');
            sessionStorage.removeItem('dtr_employee');
            return null;
        }
    }
    const employee = ref(resolveEmployee());

    const isAuthenticated = computed(() => !!token.value);
    const isSuperAdmin    = computed(() => employee.value?.role === 'super_admin');
    const isAdmin         = computed(() => employee.value?.role === 'admin' || isSuperAdmin.value);
    const isHrOfficer     = computed(() => employee.value?.role === 'hr_officer');
    const isDeptHead      = computed(() => employee.value?.role === 'department_head');

    function setAuth(data, remember = true) {
        token.value    = data.token;
        employee.value = data.employee;
        const storage  = remember ? localStorage : sessionStorage;
        const opposite = remember ? sessionStorage : localStorage;
        storage.setItem('dtr_token', data.token);
        storage.setItem('dtr_employee', JSON.stringify(data.employee));
        opposite.removeItem('dtr_token');
        opposite.removeItem('dtr_employee');
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`;
    }

    async function login(email, password, remember = true, turnstileToken = null) {
        const res = await axios.post('/api/auth/login', { email, password, turnstile_token: turnstileToken });
        setAuth(res.data, remember);
        return res.data;
    }

    // The login payload is cached in storage, so feature grants the Super
    // Admin changes in Access Control (or an employment-status change) would
    // otherwise only take effect at the next sign-in. Refresh just those
    // fields from the server; the rest of the cached profile stays as is.
    async function refreshAccess() {
        if (!token.value || !employee.value) return;
        try {
            const res = await axios.get('/api/auth/me');
            employee.value = {
                ...employee.value,
                feature_permissions: res.data.feature_permissions,
                semi_monthly_dtr:    res.data.semi_monthly_dtr,
            };
            const storage = localStorage.getItem('dtr_token') ? localStorage : sessionStorage;
            storage.setItem('dtr_employee', JSON.stringify(employee.value));
        } catch {
            // Keep the cached grants — the server enforces them regardless.
        }
    }

    async function logout() {
        try {
            await axios.post('/api/auth/logout');
        } finally {
            token.value    = null;
            employee.value = null;
            localStorage.removeItem('dtr_token');
            localStorage.removeItem('dtr_employee');
            sessionStorage.removeItem('dtr_token');
            sessionStorage.removeItem('dtr_employee');
            delete axios.defaults.headers.common['Authorization'];
            // A second account signing in on the same browser session must
            // never see the previous user's cached, campus-scoped lists.
            useReferenceDataStore().reset();
        }
    }

    // Restore axios header on app load (token already resolved from either storage above)
    if (token.value) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    }

    return { token, employee, isAuthenticated, isSuperAdmin, isAdmin, isHrOfficer, isDeptHead, login, logout, refreshAccess };
});
