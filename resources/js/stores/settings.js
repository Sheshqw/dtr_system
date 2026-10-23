import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

// The bundled fallback — used until the settings load, and whenever no
// custom logo has been uploaded.
const DEFAULT_LOGO = '/nemsu.png';

export const useSettingsStore = defineStore('settings', () => {
    const systemName = ref('NEMSU');
    const shortName   = ref('UDTRMS');
    const tagline     = ref('University Daily Time Record Monitoring System');
    const logoUrl     = ref(DEFAULT_LOGO);
    // null = the login page draws its bundled illustrated background.
    const loginBackgroundUrl = ref(null);
    const turnstileEnabled = ref(false);
    const turnstileSiteKey = ref(null);
    const loaded      = ref(false);

    async function fetch() {
        try {
            const res = await axios.get('/api/system-settings');
            systemName.value = res.data.system_name;
            shortName.value  = res.data.short_name;
            tagline.value    = res.data.tagline;
            logoUrl.value    = res.data.logo_url || DEFAULT_LOGO;
            loginBackgroundUrl.value = res.data.login_background_url || null;
            turnstileEnabled.value = !!res.data.turnstile?.enabled;
            turnstileSiteKey.value = res.data.turnstile?.site_key ?? null;
        } catch {
            // Keep defaults — a page with the fallback NEMSU branding is
            // always better than a broken one.
        } finally {
            loaded.value = true;
        }
    }

    return { systemName, shortName, tagline, logoUrl, loginBackgroundUrl, turnstileEnabled, turnstileSiteKey, loaded, fetch };
});
