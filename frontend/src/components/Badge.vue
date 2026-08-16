<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'success' | 'warning' | 'danger' }>(), { tone: 'neutral' });
</script>

<template><span class="badge" :class="`badge-${tone}`"><slot /></span></template>

<style scoped>
.badge { display: inline-flex; align-items: center; min-height: 1.5rem; padding: .25rem .55rem; border-radius: 999px; font-size: .82rem; font-weight: 700; line-height: 1; }
.badge-neutral { background: color-mix(in srgb, var(--app-muted, #50634d) 14%, transparent); color: var(--app-text, #243127); }
.badge-success { background: #edf4e8; color: #395330; }
.badge-warning { background: #fff4d9; color: #73500d; }
.badge-danger { background: #fff0ee; color: #8f1e1e; }
</style>
