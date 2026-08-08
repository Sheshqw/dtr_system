<template>
  <div class="es-wrap">
    <div class="es-icon-ring">
      <svg viewBox="0 0 48 48" fill="none" class="es-svg" aria-hidden="true">
        <rect x="10" y="14" width="28" height="28" rx="4" stroke="currentColor" stroke-width="2.5"/>
        <path d="M18 14a6 6 0 0 1 12 0" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="17" y1="25" x2="31" y2="25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <line x1="17" y1="32" x2="26" y2="32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <circle cx="33" cy="35" r="5.5" fill="currentColor" opacity="0.12"/>
        <line x1="30" y1="35" x2="36" y2="35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
    <p class="es-title">{{ title }}</p>
    <p class="es-body">{{ description }}</p>
  </div>
</template>

<script setup>
defineProps({
  title:       { type: String, default: 'No records found' },
  description: { type: String, default: 'No data matches your current filters.' },
});
</script>

<style scoped>
.es-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 52px 24px;
  gap: 10px;
}
.es-icon-ring {
  width: 68px;
  height: 68px;
  border-radius: 20px;
  background: #EEF1FF;
  border: 1.5px solid #D4DEFF;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4F6EF7;
  margin-bottom: 6px;
  animation: esFloat 4.5s ease-in-out infinite;
  flex-shrink: 0;
}
.es-svg   { width: 34px; height: 34px; }
.es-title { font-size: 14px; font-weight: 600; color: #0F172A; margin: 0; }
.es-body  { font-size: 12px; color: #94A3B8; margin: 0; text-align: center; max-width: 280px; line-height: 1.5; }

@keyframes esFloat {
  0%, 100% { transform: translateY(0px);  }
  50%       { transform: translateY(-6px); }
}

:global([data-theme="dark"]) .es-icon-ring { background: rgba(99,102,241,0.12); border-color: rgba(99,102,241,0.22); }
:global([data-theme="dark"]) .es-title     { color: #E2E8F0; }
:global([data-theme="dark"]) .es-body      { color: #64748B; }
</style>
