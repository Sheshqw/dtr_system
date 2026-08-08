import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;

// Redirect to login on expired/revoked token
axios.interceptors.response.use(
    res => res,
    err => {
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

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key:          import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:       import.meta.env.VITE_REVERB_HOST,
    wsPort:       import.meta.env.VITE_REVERB_PORT  ?? 80,
    wssPort:      import.meta.env.VITE_REVERB_PORT  ?? 443,
    forceTLS:    (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
