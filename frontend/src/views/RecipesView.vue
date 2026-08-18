<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import PageHeader from '@/components/PageHeader.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import SearchBar from '@/components/SearchBar.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchRecipes, type Recipe, type RecipePagination } from '@/utils/recipes';

const authStore = useAuthStore();
const recipes = ref<Recipe[]>([]);
const search = ref('');
const pagination = ref<RecipePagination | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

async function loadRecipes(page = 1): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken, page, 'public', '', {}, 20);
  if (result.ok) {
    recipes.value = result.recipes;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

function retry(): void {
  void loadRecipes(pagination.value?.current_page ?? 1);
}

function openSearch(): void {
  const query = search.value.trim();
  window.location.assign(query ? `/recherche?q=${encodeURIComponent(query)}` : '/recherche');
}

onMounted(() => { void loadRecipes(); });
</script>

<template>
  <main class="recipes-page">
    <PageHeader
      title="Recettes"
      description="Découvrez et organisez vos recettes."
      eyebrow="Mon espace"
      :back-to="{ name: 'dashboard' }"
      back-label="Retour au tableau de bord"
    >
      <template #primary>
        <RouterLink class="create-link" :to="{ name: 'recipe-create' }">Nouvelle recette</RouterLink>
      </template>
    </PageHeader>

    <section class="recipe-discovery" aria-labelledby="recipe-discovery-title">
      <div class="discovery-heading">
        <div>
          <h2 id="recipe-discovery-title">Trouver une recette</h2>
          <p>Recherchez dans vos recettes et accédez aux filtres avancés.</p>
        </div>
        <div class="discovery-links">
          <RouterLink class="secondary-link" :to="{ name: 'search' }">Ouvrir les filtres</RouterLink>
          <RouterLink class="secondary-link" :to="{ name: 'search', query: { favorites: 'true' } }">Mes favoris</RouterLink>
        </div>
      </div>
      <SearchBar v-model="search" @submit="openSearch" />
    </section>

    <LoadingState v-if="isLoading" label="Chargement des recettes..." />
    <ErrorState v-else-if="errorMessage" :message="errorMessage" show-retry @retry="retry" />
    <EmptyState
      v-else-if="recipes.length === 0"
      title="Aucune recette pour le moment"
      description="Créez votre première recette pour la retrouver ici."
    >
      <template #actions>
        <RouterLink class="create-link" :to="{ name: 'recipe-create' }">Créer une recette</RouterLink>
      </template>
    </EmptyState>
    <template v-else>
      <div class="recipe-grid" aria-label="Mes recettes">
        <RecipeCard v-for="recipe in recipes" :key="recipe.id" :recipe="recipe" />
      </div>
      <PaginationControls v-if="pagination && pagination.last_page > 1" :pagination="pagination" :disabled="isLoading" @change="loadRecipes" />
    </template>

  </main>
</template>

<style scoped>
.recipes-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.create-link { display: inline-block; width: fit-content; padding: .65rem .85rem; border: 1px solid var(--color-primary); border-radius: var(--radius-md); background: var(--color-primary); color: #fffdf9; font-weight: 700; text-decoration: none; transition: background-color .16s ease, border-color .16s ease; }
.create-link:hover, .create-link:focus-visible { border-color: var(--color-primary-hover); background: var(--color-primary-hover); color: #fffdf9; }
.recipe-discovery { margin: 2rem 0 1.5rem; padding: 1.25rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface-subtle); }
.discovery-heading { display: flex; align-items: center; justify-content: space-between; gap: 1.25rem; }
.discovery-heading h2 { margin: 0; color: var(--color-text); font-size: 1.2rem; }
.discovery-heading p { margin: .35rem 0 0; color: var(--color-text-secondary); line-height: 1.45; }
.discovery-links { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; }
.secondary-link { padding: .55rem .7rem; border: 1px solid var(--color-border-strong); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-primary); font-weight: 700; text-decoration: none; white-space: nowrap; transition: background-color .16s ease, border-color .16s ease; }
.secondary-link:hover, .secondary-link:focus-visible { border-color: var(--color-primary); background: var(--color-primary-soft); color: var(--color-primary-hover); }
.recipe-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; margin-top: 1.75rem; }
@media (max-width: 72rem) { .recipe-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 58rem) { .recipe-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 46rem) { .recipe-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 38rem) { .discovery-heading { align-items: flex-start; flex-direction: column; } .discovery-links { justify-content: start; } .recipe-grid { grid-template-columns: 1fr; } }
</style>
