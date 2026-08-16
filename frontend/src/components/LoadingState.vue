<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Chargement…' });
</script>

<template>
  <p class="loading-state" role="status" aria-live="polite">
    <span class="loading-indicator" aria-hidden="true" />
    <span>{{ label }}</span>
  </p>
</template>

<style scoped>
.loading-state { display: flex; align-items: center; justify-content: center; gap: .65rem; margin: 1.5rem 0; padding: 1.25rem; color: var(--app-muted, #50634d); }
.loading-indicator { width: 1rem; height: 1rem; border: .15rem solid color-mix(in srgb, var(--app-muted, #50634d) 25%, transparent); border-top-color: var(--app-muted, #50634d); border-radius: 50%; animation: loading-state-spin .8s linear infinite; }
@keyframes loading-state-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .loading-indicator { animation: none; } }
</style>
