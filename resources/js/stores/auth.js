import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

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
        }
    }

    // Restore axios header on app load (token already resolved from either storage above)
    if (token.value) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    }

    return { token, employee, isAuthenticated, isSuperAdmin, isAdmin, isHrOfficer, isDeptHead, login, logout };
});
