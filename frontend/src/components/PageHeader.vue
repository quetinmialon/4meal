<script setup lang="ts">
import { useId } from 'vue';
import type { RouteLocationRaw } from 'vue-router';

withDefaults(defineProps<{
  title: string;
  description?: string;
  eyebrow?: string;
  backTo?: RouteLocationRaw | undefined;
  backLabel?: string;
}>(), {
  description: '',
  eyebrow: '',
  backTo: undefined,
  backLabel: 'Retour',
});

const titleId = `page-header-title-${useId()}`;
</script>

<template>
  <header class="page-header" :aria-labelledby="titleId">
    <div class="page-header-content">
      <RouterLink v-if="backTo" class="page-header-back" :to="backTo">{{ backLabel }}</RouterLink>
      <p v-if="eyebrow" class="page-header-eyebrow">{{ eyebrow }}</p>
      <h1 :id="titleId">{{ title }}</h1>
      <p v-if="description" class="page-header-description">{{ description }}</p>
    </div>
    <div v-if="$slots.primary || $slots.actions" class="page-header-actions">
      <div v-if="$slots.actions" class="page-header-secondary-actions"><slot name="actions" /></div>
      <div v-if="$slots.primary" class="page-header-primary-action"><slot name="primary" /></div>
    </div>
  </header>
</template>

<style scoped>
.page-header { display: flex; align-items: end; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
.page-header-content { min-width: 0; }
.page-header-back { display: inline-block; margin-bottom: 1.25rem; color: #395330; font-weight: 700; }
.page-header-eyebrow { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1.05; }
.page-header-description { max-width: 42rem; margin: .65rem 0 0; color: #50634d; line-height: 1.5; }
.page-header-actions { display: flex; align-items: center; flex-wrap: wrap; justify-content: end; gap: .6rem; }
.page-header-secondary-actions, .page-header-primary-action { display: flex; align-items: center; gap: .6rem; }
@media (max-width: 38rem) { .page-header { align-items: flex-start; flex-direction: column; } .page-header-actions { justify-content: start; width: 100%; } }
</style>
