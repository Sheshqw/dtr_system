import { defineStore } from 'pinia';
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * Reads the effective feature grants the backend already computed and sent
 * with the logged-in employee (role default, overridden by any per-user
 * row in feature_permissions) — this store never re-derives that logic,
 * it just exposes it for v-if / route guards.
 */
export const usePermissionsStore = defineStore('permissions', () => {
    const auth = useAuthStore();

    const grants = computed(() => auth.employee?.feature_permissions ?? {});

    function can(featureKey) {
        return !!grants.value[featureKey];
    }

    return { grants, can };
});
