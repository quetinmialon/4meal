<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import RecipeEditForm from '@/components/RecipeEditForm.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchRecipe, type Recipe } from '@/utils/recipes';

const route = useRoute();
const authStore = useAuthStore();
const recipe = ref<Recipe | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

async function loadRecipe(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipe(String(route.params.id), authStore.tokenType, authStore.accessToken);
  if (result.ok) recipe.value = result.recipe;
  else errorMessage.value = result.message;
  isLoading.value = false;
}

onMounted(() => { void loadRecipe(); });
</script>

<template>
  <main class="edit-page">
    <RouterLink class="back-link" :to="{ name: 'recipe-detail', params: { id: route.params.id } }">Retour à la recette</RouterLink>
    <p class="kicker">Modification</p>
    <h2>Modifier la recette</h2>
    <p v-if="isLoading" class="state-message" role="status">Chargement de la recette...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">
      <p>{{ errorMessage }}</p>
      <button type="button" @click="loadRecipe">Réessayer</button>
    </section>
    <RecipeEditForm v-else-if="recipe" :recipe="recipe" @reload="loadRecipe" />
  </main>
</template>

<style scoped>
.edit-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.state-message { margin-top: 1.5rem; color: #50634d; }
.error-summary { padding: 1rem; border-radius: .8rem; color: #8f1e1e; background: #fff0ee; }
.error-summary button { padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
</style>
