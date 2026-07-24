<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import PaginationControls from '@/components/PaginationControls.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import SearchBar from '@/components/SearchBar.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { fetchRecipes, type Recipe, type RecipeFilters, type RecipePagination } from '@/utils/recipes';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const search = ref('');
const recipes = ref<Recipe[]>([]);
const pagination = ref<RecipePagination | null>(null);
const isLoading = ref(false);
const errorMessage = ref('');
const cookbooks = ref<Cookbook[]>([]);
const cookbooksLoading = ref(false);
const cookbooksError = ref('');
const cookbookId = ref('');
const tag = ref('');
const ingredient = ref('');
const maxPrepTime = ref('');
const maxCookTime = ref('');
const favorites = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | undefined;
let requestId = 0;

function queryString(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function currentPage(): number {
  const page = Number.parseInt(queryString(route.query.page), 10);
  return Number.isInteger(page) && page > 0 ? page : 1;
}

function queryNumber(value: unknown): string {
  const parsed = Number.parseInt(queryString(value), 10);
  return Number.isInteger(parsed) && parsed >= 0 ? String(parsed) : '';
}

function queryBoolean(value: unknown): boolean {
  return ['1', 'true'].includes(queryString(value).toLowerCase());
}

const hasFilters = computed(() => Boolean(
  cookbookId.value || tag.value.trim() || ingredient.value.trim() || maxPrepTime.value || maxCookTime.value || favorites.value,
));

function filtersForRequest(): RecipeFilters {
  return {
    ...(cookbookId.value ? { cookbook_id: cookbookId.value } : {}),
    ...(tag.value.trim() ? { tag: tag.value.trim() } : {}),
    ...(ingredient.value.trim() ? { ingredient: ingredient.value.trim() } : {}),
    ...(maxPrepTime.value ? { max_prep_time: Number(maxPrepTime.value) } : {}),
    ...(maxCookTime.value ? { max_cook_time: Number(maxCookTime.value) } : {}),
    ...(favorites.value ? { favorites: true } : {}),
  };
}

function queryForCurrentState(page?: string) {
  return {
    ...route.query,
    q: search.value.trim() || undefined,
    cookbook_id: cookbookId.value || undefined,
    tag: tag.value.trim() || undefined,
    ingredient: ingredient.value.trim() || undefined,
    max_prep_time: maxPrepTime.value || undefined,
    max_cook_time: maxCookTime.value || undefined,
    favorites: favorites.value ? 'true' : undefined,
    page,
  };
}

function scheduleLoad(): void {
  if (debounceTimer !== undefined) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => { void loadSearch(); }, 350);
}

async function loadSearch(): Promise<void> {
  const term = search.value.trim();
  if (term === '' && !hasFilters.value) {
    recipes.value = [];
    pagination.value = null;
    errorMessage.value = '';
    isLoading.value = false;
    return;
  }

  const activeRequest = ++requestId;
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken, currentPage(), 'all', term, filtersForRequest());
  if (activeRequest !== requestId) return;

  if (result.ok) {
    recipes.value = result.recipes;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

function syncSearch(value: string): void {
  void router.replace({ query: { ...queryForCurrentState(), q: value.trim() || undefined, page: undefined } });
  scheduleLoad();
}

function submitSearch(): void {
  syncSearch(search.value);
}

function changePage(page: number): void {
  void router.push({ query: queryForCurrentState(String(page)) });
}

function syncFilters(): void {
  void router.replace({ query: queryForCurrentState() });
  scheduleLoad();
}

function resetFilters(): void {
  search.value = '';
  cookbookId.value = '';
  tag.value = '';
  ingredient.value = '';
  maxPrepTime.value = '';
  maxCookTime.value = '';
  favorites.value = false;
  void router.replace({ query: {} });
  scheduleLoad();
}

function retry(): void {
  void loadSearch();
}

watch(
  () => [route.query.q, route.query.page, route.query.cookbook_id, route.query.tag, route.query.ingredient, route.query.max_prep_time, route.query.max_cook_time, route.query.favorites],
  () => {
    search.value = queryString(route.query.q);
    cookbookId.value = queryString(route.query.cookbook_id);
    tag.value = queryString(route.query.tag);
    ingredient.value = queryString(route.query.ingredient);
    maxPrepTime.value = queryNumber(route.query.max_prep_time);
    maxCookTime.value = queryNumber(route.query.max_cook_time);
    favorites.value = queryBoolean(route.query.favorites);
    scheduleLoad();
  },
  { immediate: true },
);

onMounted(async () => {
  cookbooksLoading.value = true;
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) cookbooks.value = result.data;
  else cookbooksError.value = result.message;
  cookbooksLoading.value = false;
});

onBeforeUnmount(() => {
  if (debounceTimer !== undefined) clearTimeout(debounceTimer);
  requestId++;
});
</script>

<template>
  <main class="search-page">
    <div class="page-heading">
      <div>
        <RouterLink class="back-link" :to="{ name: 'dashboard' }">Retour au tableau de bord</RouterLink>
        <p class="kicker">Explorer</p>
        <h2>Recherche de recettes</h2>
      </div>
      <RouterLink class="secondary-link" :to="{ name: 'recipes' }">Mes recettes</RouterLink>
    </div>

    <SearchBar v-model="search" :disabled="isLoading" @update:model-value="syncSearch" @submit="submitSearch" />

    <section class="filters-panel" aria-labelledby="filters-title">
      <div class="filters-heading">
        <h3 id="filters-title">Filtrer les recettes</h3>
        <button type="button" class="reset-button" :disabled="isLoading || !hasFilters" @click="resetFilters">Réinitialiser</button>
      </div>
      <p v-if="cookbooksLoading" class="filter-status" role="status">Chargement des cookbooks...</p>
      <p v-else-if="cookbooksError" class="filter-status filter-error" role="alert">{{ cookbooksError }}</p>
      <div class="filters-grid">
        <label>
          Cookbook
          <select v-model="cookbookId" :disabled="isLoading || cookbooksLoading" @change="syncFilters">
            <option value="">Tous les cookbooks</option>
            <option v-for="cookbook in cookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
          </select>
        </label>
        <label>
          Tag
          <input v-model="tag" type="text" placeholder="Ex. rapide" :disabled="isLoading" @change="syncFilters" @keyup.enter="syncFilters" />
        </label>
        <label>
          Ingrédient
          <input v-model="ingredient" type="text" placeholder="Ex. tomates" :disabled="isLoading" @change="syncFilters" @keyup.enter="syncFilters" />
        </label>
        <label>
          Préparation maximale (min.)
          <input v-model="maxPrepTime" type="number" min="0" max="10080" placeholder="Aucune limite" :disabled="isLoading" @change="syncFilters" />
        </label>
        <label>
          Cuisson maximale (min.)
          <input v-model="maxCookTime" type="number" min="0" max="10080" placeholder="Aucune limite" :disabled="isLoading" @change="syncFilters" />
        </label>
        <label class="checkbox-filter">
          <input v-model="favorites" type="checkbox" :disabled="isLoading" @change="syncFilters" />
          <span>Mes favoris uniquement</span>
        </label>
      </div>
    </section>

    <p v-if="isLoading" class="state-message" role="status" aria-live="polite">Recherche en cours...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">
      <p>{{ errorMessage }}</p>
      <button type="button" @click="retry">Réessayer</button>
    </section>
    <section v-else-if="search.trim() === '' && !hasFilters" class="empty-state" aria-live="polite">
      <h3>Que cherchez-vous ?</h3>
      <p>Saisissez un titre, un ingrédient, un tag ou un élément d'instruction.</p>
    </section>
    <section v-else-if="recipes.length === 0" class="empty-state" aria-live="polite">
      <h3>Aucune recette trouvée</h3>
      <p>Essayez avec un autre terme de recherche.</p>
    </section>
    <template v-else>
      <p class="result-count" aria-live="polite">{{ pagination?.total ?? recipes.length }} résultat{{ (pagination?.total ?? recipes.length) > 1 ? 's' : '' }}</p>
      <div class="recipe-grid" aria-label="Résultats de recherche">
        <RecipeCard v-for="recipe in recipes" :key="recipe.id" :recipe="recipe" />
      </div>
      <PaginationControls v-if="pagination && pagination.last_page > 1" :pagination="pagination" :disabled="isLoading" @change="changePage" />
    </template>
  </main>
</template>

<style scoped>
.search-page { max-width: 52rem; margin: 0 auto; }
.page-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.secondary-link { padding: .65rem .8rem; border: 1px solid #395330; border-radius: .6rem; color: #395330; font-weight: 700; text-decoration: none; }
.recipe-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
.filters-panel { margin-top: 1.5rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; background: rgba(247, 251, 243, .65); }
.filters-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.filters-heading h3 { margin: 0; color: #243127; }
.reset-button { padding: .45rem .65rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.reset-button:disabled { cursor: not-allowed; opacity: .45; }
.filters-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; margin-top: 1rem; }
.filters-grid label { display: grid; gap: .35rem; color: #395330; font-size: .9rem; font-weight: 700; }
.filters-grid input:not([type="checkbox"]), .filters-grid select { width: 100%; box-sizing: border-box; padding: .65rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; color: #243127; font: inherit; font-weight: 400; }
.checkbox-filter { display: flex !important; align-items: center; grid-template-columns: auto 1fr; gap: .55rem !important; align-self: end; min-height: 2.4rem; }
.checkbox-filter input { width: 1.1rem; height: 1.1rem; accent-color: #395330; }
.filter-status { margin: .8rem 0 0; color: #50634d; font-size: .9rem; }
.filter-error { color: #8f1e1e; }
.result-count { margin: 1.5rem 0 0; color: #50634d; font-size: .9rem; }
.state-message, .empty-state { margin-top: 1.5rem; padding: 1.25rem; border-radius: .8rem; }
.state-message { color: #50634d; }
.error-summary { color: #8f1e1e; background: #fff0ee; }
.error-summary button { padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.empty-state { border: 1px dashed #b9c5af; color: #50634d; }
.empty-state h3 { margin-top: 0; color: #243127; }
@media (max-width: 38rem) { .page-heading { align-items: flex-start; flex-direction: column; } .filters-grid { grid-template-columns: 1fr; } .recipe-grid { grid-template-columns: 1fr; } }
</style>
