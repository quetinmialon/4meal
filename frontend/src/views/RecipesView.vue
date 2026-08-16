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
  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken, page, 'mine');
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
      title="Mes recettes"
      description="Découvrez et organisez vos recettes."
      eyebrow="Mon espace"
      :back-to="{ name: 'dashboard' }"
      back-label="Retour au tableau de bord"
    >
      <template #primary>
        <RouterLink class="create-link" :to="{ name: 'recipe-create' }">Nouvelle recette</RouterLink>
      </template>
    </PageHeader>

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
  </main>
</template>

<style scoped>
.recipes-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.create-link { display: inline-block; width: fit-content; padding: .65rem .8rem; border: 1px solid #395330; border-radius: .6rem; background: #395330; color: #fffdf8; font-weight: 700; text-decoration: none; }
.recipe-discovery { margin: 1.5rem 0; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .9rem; background: rgba(247,251,243,.65); }
.discovery-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.discovery-heading h2 { margin: 0; color: #243127; font-size: 1.15rem; }
.discovery-heading p { margin: .35rem 0 0; color: #50634d; line-height: 1.45; }
.discovery-links { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; }
.secondary-link { padding: .55rem .7rem; border: 1px solid #395330; border-radius: .55rem; color: #395330; font-weight: 700; text-decoration: none; white-space: nowrap; }
.recipe-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1.5rem; }
@media (max-width: 38rem) { .discovery-heading { align-items: flex-start; flex-direction: column; } .discovery-links { justify-content: start; } .recipe-grid { grid-template-columns: 1fr; } }
</style>
