import axios from 'axios';
import { reportResponse, reportRequestError } from '@/composables/useConnectionStatus';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;

// Redirect to login on expired/revoked token, and keep the app-wide
// connection banner (useConnectionStatus) in sync with what requests see.
axios.interceptors.response.use(
    res => {
        reportResponse();
        return res;
    },
    err => {
        reportRequestError(err);

        if (err.response?.status === 401) {
            localStorage.removeItem('dtr_token');
            localStorage.removeItem('dtr_employee');
            sessionStorage.removeItem('dtr_token');
            sessionStorage.removeItem('dtr_employee');
            delete axios.defaults.headers.common['Authorization'];
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(err);
    }
);

// Echo (real-time updates) is loaded on demand after sign-in — see echo.js.
