<template>
  <div class="ph-wrap">
    <div class="ph-texture" aria-hidden="true"></div>
    <div class="ph-shimmer" aria-hidden="true"></div>

    <div class="ph-body">
      <div class="ph-left">
        <div class="ph-icon-badge" v-if="icon">
          <component :is="icon" class="w-5 h-5" />
        </div>
        <div>
          <p class="ph-crumb">{{ settings.systemName }} · {{ settings.shortName }}</p>
          <h1 class="ph-title">{{ title }}</h1>
          <p v-if="subtitle" class="ph-sub">{{ subtitle }}</p>
        </div>
      </div>

      <div class="ph-actions">
        <slot />
        <NotificationBell />
      </div>
    </div>
  </div>
</template>

<script setup>
import NotificationBell from '@/components/NotificationBell.vue';
import { useSettingsStore } from '@/stores/settings';

const settings = useSettingsStore();

defineProps({
  title:    { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon:     { type: Object, default: null },
});
</script>

<style scoped>
.ph-wrap {
  position: relative;
  padding: 18px 24px;
  overflow: hidden;
  flex-shrink: 0;
  background: linear-gradient(135deg, #080E80 0%, #1020BC 55%, #0E1EC0 100%);
}

/* Dot-grid texture */
.ph-texture {
  position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,0.035) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Diagonal gold shimmer stripe */
.ph-shimmer {
  position: absolute;
  top: -80%; right: 0%;
  width: 38%; height: 280%;
  background: linear-gradient(
    108deg,
    transparent 35%,
    rgba(201,162,39,0.07) 50%,
    transparent 65%
  );
  transform: skewX(-6deg);
  pointer-events: none;
}

.ph-body {
  position: relative;
  display: flex; align-items: center;
  justify-content: space-between; gap: 16px;
  flex-wrap: wrap;
}

.ph-left {
  display: flex; align-items: center; gap: 14px;
}

.ph-icon-badge {
  width: 44px; height: 44px;
  border-radius: 12px; flex-shrink: 0;
  background: rgba(201,162,39,0.14);
  border: 1px solid rgba(201,162,39,0.32);
  display: flex; align-items: center; justify-content: center;
  color: #C9A227;
  box-shadow: 0 0 0 4px rgba(201,162,39,0.06);
}

.ph-crumb {
  font-size: 9.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.15em;
  color: rgba(201,162,39,0.55);
  margin-bottom: 4px;
}

.ph-title {
  font-size: 19px; font-weight: 700;
  color: #FFFFFF;
  letter-spacing: -0.015em;
  line-height: 1.15;
}

.ph-sub {
  font-size: 12px; margin-top: 4px;
  color: rgba(157,173,216,0.75);
  line-height: 1.4;
}

/* Slot actions — buttons inside here get a glass style */
.ph-actions {
  flex-shrink: 0;
  display: flex; align-items: center; gap: 8px;
}

/* Override any btn inside actions slot to fit dark header */
.ph-actions :deep(button),
.ph-actions :deep(a[role="button"]) {
  background: rgba(255,255,255,0.1) !important;
  border: 1px solid rgba(255,255,255,0.2) !important;
  color: #fff !important;
  border-radius: 8px;
}
.ph-actions :deep(button:hover:not(:disabled)),
.ph-actions :deep(a[role="button"]:hover) {
  background: rgba(255,255,255,0.18) !important;
  border-color: rgba(255,255,255,0.32) !important;
}
.ph-actions :deep(button:disabled) {
  opacity: 0.45 !important;
  cursor: not-allowed;
}

/* Primary accent button (add/create) — gold style */
.ph-actions :deep(.ph-btn-primary) {
  background: rgba(201,162,39,0.2) !important;
  border-color: rgba(201,162,39,0.45) !important;
  color: #E8C040 !important;
}
.ph-actions :deep(.ph-btn-primary:hover:not(:disabled)) {
  background: rgba(201,162,39,0.32) !important;
  border-color: rgba(201,162,39,0.65) !important;
}

/* ── Mobile ─────────────────────────────────────────── */
@media (max-width: 640px) {
  .ph-wrap { padding: 14px 16px; }
  .ph-body { flex-direction: column; align-items: flex-start; gap: 10px; }
  .ph-title { font-size: 15px; }
  .ph-crumb { font-size: 8.5px; margin-bottom: 2px; }
  .ph-sub   { font-size: 11px; }
  .ph-actions { width: 100%; }
  .ph-actions :deep(button),
  .ph-actions :deep(a[role="button"]) { font-size: 12px !important; padding: 6px 12px !important; }
  .ph-icon-badge { width: 36px; height: 36px; border-radius: 9px; }
}
</style>
