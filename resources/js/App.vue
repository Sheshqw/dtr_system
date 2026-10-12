<template>
  <RouterView />
  <ConnectionBanner />
</template>

<script setup>
import { RouterView } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import ConnectionBanner from '@/components/ConnectionBanner.vue';

// Fetched once, as early as possible — the login page needs branding
// before anyone is authenticated, so this can't wait for AppLayout to mount.
useSettingsStore().fetch();
</script>
