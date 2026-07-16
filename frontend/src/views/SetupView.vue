<script setup lang="ts">
import AppStatus from '@/components/AppStatus.vue';
import { useAppStore } from '@/stores/app';

const appStore = useAppStore();
</script>

<template>
  <main class="setup-card">
    <AppStatus :label="appStore.statusLabel" />
    <p class="summary">
      Vue Router, Pinia, ESLint, Prettier and Vitest are wired into a strict TypeScript setup.
    </p>
  </main>
</template>

<style scoped>
.setup-card {
  margin: 0 auto;
  max-width: 48rem;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.88);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.08);
}

.summary {
  margin: 1rem 0 0;
  max-width: 36rem;
  font-size: 1.05rem;
  line-height: 1.6;
}
</style>
