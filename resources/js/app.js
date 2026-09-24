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
