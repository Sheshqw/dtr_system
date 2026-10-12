import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { useThemeStore } from './stores/theme';
import { useTextSizeStore } from './stores/textSize';
import './bootstrap';
import '../css/app.css';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

// Init theme before mount so isDark is reactive from the start
useThemeStore(pinia).init();
useTextSizeStore(pinia).init();

app.mount('#app');

// Offline page for page loads with no connection (see public/sw.js — it
// caches nothing but that page). Production builds only; browsers allow
// service workers only on HTTPS or localhost, so elsewhere this is a no-op.
if (import.meta.env.PROD && 'serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Not supported or blocked — the browser's own offline screen still applies.
        });
    });
}
