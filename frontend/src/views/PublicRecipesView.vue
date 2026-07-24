<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import PaginationControls from '@/components/PaginationControls.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchRecipes, type Recipe, type RecipePagination } from '@/utils/recipes';

const authStore = useAuthStore();
const recipes = ref<Recipe[]>([]);
const pagination = ref<RecipePagination | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

async function loadRecipes(page = 1): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken, page, 'public');
  if (result.ok) {
    recipes.value = result.recipes;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

onMounted(() => { void loadRecipes(); });
</script>

<template>
  <main class="public-recipes-page">
    <div class="page-heading">
      <div>
        <RouterLink class="back-link" :to="{ name: 'dashboard' }">Retour au tableau de bord</RouterLink>
        <p class="kicker">Découvrir</p>
        <h2>Toutes les recettes</h2>
      </div>
      <RouterLink class="my-recipes-link" :to="{ name: 'recipes' }">Mes recettes</RouterLink>
    </div>

    <p v-if="isLoading" class="state-message" role="status">Chargement des recettes...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">{{ errorMessage }}</section>
    <section v-else-if="recipes.length === 0" class="empty-state">
      <h3>Aucune recette publique pour le moment</h3>
      <p>Les recettes personnelles apparaîtront ici.</p>
    </section>
    <template v-else>
      <div class="recipe-grid">
        <RecipeCard v-for="recipe in recipes" :key="recipe.id" :recipe="recipe" />
      </div>
      <PaginationControls v-if="pagination && pagination.last_page > 1" :pagination="pagination" :disabled="isLoading" @change="loadRecipes" />
    </template>
  </main>
</template>

<style scoped>
.public-recipes-page { max-width: 52rem; margin: 0 auto; }
.page-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.my-recipes-link { display: inline-block; padding: .65rem .8rem; border: 1px solid #395330; border-radius: .6rem; color: #395330; font-weight: 700; text-decoration: none; }
.recipe-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1.5rem; }
.state-message, .empty-state { margin-top: 1.5rem; padding: 1.25rem; border-radius: .8rem; }
.state-message { color: #50634d; }
.error-summary { color: #8f1e1e; background: #fff0ee; }
.empty-state { border: 1px dashed #b9c5af; color: #50634d; }
.empty-state h3 { margin-top: 0; color: #243127; }
@media (max-width: 38rem) { .page-heading { align-items: flex-start; flex-direction: column; } .recipe-grid { grid-template-columns: 1fr; } }
</style>
