<template>
  <div>
    <PageHeader title="Page not found" subtitle="The address you opened doesn't match any page" :icon="CompassIcon" />

    <div class="px-4 sm:px-6 py-10 flex justify-center">
      <div class="nf-card">
        <div class="nf-bar" aria-hidden="true"></div>
        <div class="nf-body">
          <span class="nf-code">Error 404</span>
          <h2 class="nf-title">We couldn't find that page</h2>
          <p class="nf-text">
            <code class="nf-path">{{ route.fullPath }}</code>
            doesn't exist or may have been moved. Check the address, or use one of the links below.
          </p>

          <div class="nf-actions">
            <RouterLink :to="{ name: 'dashboard' }" class="nf-btn nf-btn--primary">
              <LayoutDashboardIcon class="w-4 h-4" />
              Go to Dashboard
            </RouterLink>
            <button v-if="canGoBack" type="button" class="nf-btn nf-btn--ghost" @click="router.back()">
              <ArrowLeftIcon class="w-4 h-4" />
              Go back
            </button>
          </div>

          <div class="nf-links">
            <span class="nf-links-label">Popular pages</span>
            <RouterLink :to="{ name: 'attendance' }" class="nf-link">Attendance</RouterLink>
            <RouterLink :to="{ name: 'reports' }" class="nf-link">Reports &amp; DTR</RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { RouterLink, useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import {
  ArrowLeft       as ArrowLeftIcon,
  Compass         as CompassIcon,
  LayoutDashboard as LayoutDashboardIcon,
} from 'lucide-vue-next';

const route  = useRoute();
const router = useRouter();

// Only offer "Go back" when there is an in-app page to return to — a link
// opened in a fresh tab has no history, and back() would leave the site.
const canGoBack = !!window.history.state?.back;
</script>

<style scoped>
.nf-card {
  width: 100%;
  max-width: 520px;
  overflow: hidden;
  text-align: center;
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
}
.nf-bar  { height: 4px; background: linear-gradient(90deg, #2E4A8C 0%, #2E4A8C 55%, #C9A227 100%); }
.nf-body { padding: 32px 28px 28px; }
.nf-code {
  display: inline-block;
  padding: 5px 14px;
  margin-bottom: 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  color: #2E4A8C;
  background: #EEF2FF;
}
.nf-title { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #0F172A; }
.nf-text  { margin: 0 auto; max-width: 400px; font-size: 14px; line-height: 1.6; color: #5B6B86; }
.nf-path {
  padding: 1px 6px;
  border-radius: 5px;
  font-size: 12.5px;
  color: #0F172A;
  background: #F1F5F9;
  word-break: break-all;
}
.nf-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-top: 24px;
}
.nf-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}
.nf-btn--primary { color: #FFFFFF; background: #2E4A8C; }
.nf-btn--primary:hover { background: #1B2A5E; }
.nf-btn--ghost { color: #0F172A; border: 1px solid #E2E8F0; background: transparent; }
.nf-btn--ghost:hover { border-color: #94A3B8; }
.nf-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 6px 14px;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid #E2E8F0;
  font-size: 13px;
}
.nf-links-label { color: #94A3B8; }
.nf-link { color: #2E4A8C; font-weight: 600; }
.nf-link:hover { text-decoration: underline; }

[data-theme="dark"] .nf-card  { background: #1C2A44; border-color: rgba(255, 255, 255, 0.10); }
[data-theme="dark"] .nf-code  { color: #93C5FD; background: rgba(96, 165, 250, 0.14); }
[data-theme="dark"] .nf-title { color: #E4EAF5; }
[data-theme="dark"] .nf-text  { color: #9AAAC9; }
[data-theme="dark"] .nf-path  { color: #E4EAF5; background: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .nf-btn--ghost { color: #E4EAF5; border-color: rgba(255, 255, 255, 0.14); }
[data-theme="dark"] .nf-links { border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .nf-link  { color: #93C5FD; }

@media (max-width: 420px) {
  .nf-body { padding: 26px 18px 22px; }
  .nf-btn  { flex: 1 1 100%; justify-content: center; }
}
</style>
