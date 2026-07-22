<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import type { Cookbook } from '@/utils/cookbooks';
import { fetchCookbook } from '@/utils/cookbooks';

const route = useRoute();
const authStore = useAuthStore();
const cookbook = ref<Cookbook | null>(null);
const errorMessage = ref('');

onMounted(async () => {
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);

  if (result.ok) {
    cookbook.value = result.cookbook;
    return;
  }

  errorMessage.value = result.message;
});
</script>

<template>
  <main class="cookbook-card">
    <RouterLink class="back-link" :to="{ name: 'dashboard' }">Retour aux cookbooks</RouterLink>
    <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
    <template v-else-if="cookbook">
      <p class="kicker">Cookbook</p>
      <h2>{{ cookbook.name }}</h2>
      <p class="detail">Proprietaire : {{ cookbook.owner.name }}</p>
    </template>
    <p v-else class="loading" role="status">Chargement...</p>
  </main>
</template>

<style scoped>
.cookbook-card { margin: 0 auto; max-width: 42rem; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.detail, .loading { margin-top: 1rem; color: #50634d; }
.error-summary { margin-top: 2rem; color: #8f1e1e; }
</style>
