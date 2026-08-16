<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
  <section class="empty-state" aria-live="polite">
    <h2>{{ title }}</h2>
    <p v-if="description">{{ description }}</p>
    <div v-if="$slots.actions" class="empty-state-actions"><slot name="actions" /></div>
  </section>
</template>

<style scoped>
.empty-state { margin: 1.5rem 0; padding: 1.5rem; border: 1px dashed var(--app-border, #b9c5af); border-radius: .8rem; color: var(--app-muted, #50634d); }
.empty-state h2 { margin: 0; color: var(--app-text, #243127); font-size: 1.15rem; }
.empty-state p { margin: .6rem 0 0; line-height: 1.5; }
.empty-state-actions { margin-top: 1rem; }
</style>
