<script setup lang="ts">
withDefaults(defineProps<{ message: string; title?: string; showRetry?: boolean }>(), { title: 'Une erreur est survenue', showRetry: false });
const emit = defineEmits<{ retry: [] }>();
</script>

<template>
  <section class="error-state" role="alert" aria-live="assertive">
    <h2>{{ title }}</h2>
    <p>{{ message }}</p>
    <button v-if="showRetry" type="button" @click="emit('retry')"><slot name="retry">Réessayer</slot></button>
  </section>
</template>

<style scoped>
.error-state { margin: 1.5rem 0; padding: 1.25rem; border: 1px solid color-mix(in srgb, #8f1e1e 28%, transparent); border-radius: .8rem; background: color-mix(in srgb, #fff0ee 80%, var(--app-surface, #fffdf8)); color: #8f1e1e; }
.error-state h2 { margin: 0; font-size: 1.1rem; }
.error-state p { margin: .6rem 0 0; line-height: 1.5; }
.error-state button { margin-top: .8rem; padding: .5rem .7rem; border: 1px solid currentColor; border-radius: .5rem; background: transparent; color: inherit; font: inherit; font-weight: 700; cursor: pointer; }
.error-state button:focus-visible { outline: 3px solid color-mix(in srgb, currentColor 35%, transparent); outline-offset: 2px; }
</style>
