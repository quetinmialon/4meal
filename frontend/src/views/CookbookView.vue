<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import type { Cookbook, Pagination, Recipe } from '@/utils/cookbooks';
import { fetchCookbook, fetchCookbookRecipes } from '@/utils/cookbooks';

const route = useRoute();
const authStore = useAuthStore();
const cookbook = ref<Cookbook | null>(null);
const errorMessage = ref('');
const recipes = ref<Recipe[]>([]);
const recipesPagination = ref<Pagination | null>(null);
const recipesError = ref('');
const recipesLoading = ref(true);

async function loadRecipes(page = 1): Promise<void> {
  recipesLoading.value = true;
  recipesError.value = '';
  const result = await fetchCookbookRecipes(String(route.params.id), authStore.tokenType, authStore.accessToken, page);

  if (result.ok) {
    recipes.value = result.data;
    recipesPagination.value = result.pagination;
  } else {
    recipesError.value = result.message;
  }
  recipesLoading.value = false;
}

async function goToRecipePage(page: number): Promise<void> {
  if (recipesPagination.value === null || page < 1 || page > recipesPagination.value.last_page) return;
  await loadRecipes(page);
}

onMounted(async () => {
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);

  if (result.ok) {
    cookbook.value = result.cookbook;
    await loadRecipes();
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
      <p class="role-line">Votre rôle : <strong>{{ cookbook.member_role ?? 'membre' }}</strong></p>
      <section class="recipes-section" aria-labelledby="recipes-title">
        <h3 id="recipes-title">Recettes</h3>
        <p v-if="recipesLoading" role="status">Chargement des recettes...</p>
        <p v-else-if="recipesError" class="error-summary" role="alert">{{ recipesError }}</p>
        <p v-else-if="recipes.length === 0" class="empty-state">Aucune recette dans ce cookbook.</p>
        <div v-else class="recipe-list">
          <article v-for="recipe in recipes" :key="recipe.id" class="recipe-item">
            <h4>{{ recipe.name }}</h4>
            <p v-if="recipe.description">{{ recipe.description }}</p>
          </article>
          <nav v-if="recipesPagination && recipesPagination.last_page > 1" class="pagination" aria-label="Pagination des recettes">
            <button type="button" :disabled="recipesPagination.current_page === 1" @click="goToRecipePage(recipesPagination.current_page - 1)">
              Precedent
            </button>
            <span>Page {{ recipesPagination.current_page }} / {{ recipesPagination.last_page }}</span>
            <button type="button" :disabled="!recipesPagination.has_more_pages" @click="goToRecipePage(recipesPagination.current_page + 1)">
              Suivant
            </button>
          </nav>
        </div>
      </section>
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
.role-line { margin-top: 0.5rem; color: #395330; }
.recipes-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
h3 { margin: 0 0 1rem; font-size: 1.5rem; }
.empty-state { color: #50634d; }
.recipe-list { display: grid; gap: 0.7rem; }
.recipe-item { padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; }
.recipe-item h4, .recipe-item p { margin: 0; }
.recipe-item p { margin-top: 0.4rem; color: #50634d; line-height: 1.5; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 1rem; color: #50634d; font-size: 0.9rem; }
.pagination button { padding: 0.5rem 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: 0.45; }
</style>
