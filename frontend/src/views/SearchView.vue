<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import PaginationControls from '@/components/PaginationControls.vue';
import RecipeCard from '@/components/RecipeCard.vue';
import SearchBar from '@/components/SearchBar.vue';
import { useAuthStore } from '@/stores/auth';
import { fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { fetchRecipes, type Recipe, type RecipeFilters, type RecipePagination } from '@/utils/recipes';
import { createSavedSearch, deleteSavedSearch, fetchSavedSearches, type SavedSearch, type SavedSearchCriteria } from '@/utils/savedSearches';
import { useDialogFocus } from '@/utils/dialogFocus';

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
const minRating = ref('');
const ratingSort = ref('');
const savedSearches = ref<SavedSearch[]>([]);
const savedSearchesLoading = ref(false);
const savedSearchesError = ref('');
const savedSearchName = ref('');
const savedSearchActionError = ref('');
const savingSearch = ref(false);
const deletingSearchId = ref('');
const isFilterDrawerOpen = ref(false);
const filterDrawer = ref<HTMLElement | null>(null);
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
  cookbookId.value || tag.value.trim() || ingredient.value.trim() || maxPrepTime.value || maxCookTime.value || favorites.value || minRating.value || ratingSort.value,
));

const activeFilterChips = computed(() => [
  cookbookId.value ? { key: 'cookbook', label: `Cookbook : ${cookbooks.value.find((item) => item.id === cookbookId.value)?.name ?? cookbookId.value}` } : null,
  tag.value.trim() ? { key: 'tag', label: `Tag : ${tag.value.trim()}` } : null,
  ingredient.value.trim() ? { key: 'ingredient', label: `Ingrédient : ${ingredient.value.trim()}` } : null,
  maxPrepTime.value ? { key: 'prep', label: `Préparation ≤ ${maxPrepTime.value} min` } : null,
  maxCookTime.value ? { key: 'cook', label: `Cuisson ≤ ${maxCookTime.value} min` } : null,
  favorites.value ? { key: 'favorites', label: 'Mes favoris' } : null,
  minRating.value ? { key: 'rating', label: `Note ≥ ${minRating.value}/5` } : null,
  ratingSort.value ? { key: 'sort', label: ratingSort.value === 'rating_desc' ? 'Mieux notées' : 'Moins bien notées' } : null,
].filter((chip): chip is { key: string; label: string } => chip !== null));

function closeFilterDrawer(): void {
  isFilterDrawerOpen.value = false;
}

function clearFilter(key: string): void {
  if (key === 'cookbook') cookbookId.value = '';
  if (key === 'tag') tag.value = '';
  if (key === 'ingredient') ingredient.value = '';
  if (key === 'prep') maxPrepTime.value = '';
  if (key === 'cook') maxCookTime.value = '';
  if (key === 'favorites') favorites.value = false;
  if (key === 'rating') minRating.value = '';
  if (key === 'sort') ratingSort.value = '';
  syncFilters();
}

useDialogFocus(filterDrawer, isFilterDrawerOpen, closeFilterDrawer);

function filtersForRequest(): RecipeFilters {
  return {
    ...(cookbookId.value ? { cookbook_id: cookbookId.value } : {}),
    ...(tag.value.trim() ? { tag: tag.value.trim() } : {}),
    ...(ingredient.value.trim() ? { ingredient: ingredient.value.trim() } : {}),
    ...(maxPrepTime.value ? { max_prep_time: Number(maxPrepTime.value) } : {}),
    ...(maxCookTime.value ? { max_cook_time: Number(maxCookTime.value) } : {}),
    ...(favorites.value ? { favorites: true } : {}),
    ...(minRating.value ? { min_rating: Number(minRating.value) } : {}),
    ...(ratingSort.value ? { sort: ratingSort.value as 'rating_desc' | 'rating_asc' } : {}),
  };
}

function criteriaForCurrentState(): SavedSearchCriteria {
  return {
    ...(search.value.trim() ? { q: search.value.trim() } : {}),
    ...filtersForRequest(),
  };
}

function queryForCriteria(criteria: SavedSearchCriteria): Record<string, string | undefined> {
  return {
    q: criteria.q || undefined,
    cookbook_id: criteria.cookbook_id || undefined,
    tag: criteria.tag || undefined,
    ingredient: criteria.ingredient || undefined,
    max_prep_time: criteria.max_prep_time === undefined ? undefined : String(criteria.max_prep_time),
    max_cook_time: criteria.max_cook_time === undefined ? undefined : String(criteria.max_cook_time),
    favorites: criteria.favorites ? 'true' : undefined,
    min_rating: criteria.min_rating === undefined ? undefined : String(criteria.min_rating),
    sort: criteria.sort || undefined,
    page: undefined,
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
    min_rating: minRating.value || undefined,
    sort: ratingSort.value || undefined,
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
  minRating.value = '';
  ratingSort.value = '';
  void router.replace({ query: {} });
  scheduleLoad();
}

function applySavedSearch(savedSearch: SavedSearch): void {
  void router.push({ query: queryForCriteria(savedSearch.criteria) });
}

async function saveCurrentSearch(): Promise<void> {
  const name = savedSearchName.value.trim();
  if (!name) {
    savedSearchActionError.value = 'Donnez un nom à cette recherche.';
    return;
  }

  savingSearch.value = true;
  savedSearchActionError.value = '';
  const result = await createSavedSearch(name, criteriaForCurrentState(), authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    savedSearches.value = [result.savedSearch, ...savedSearches.value.filter((item) => item.id !== result.savedSearch.id)];
    savedSearchName.value = '';
  } else {
    savedSearchActionError.value = result.message;
  }
  savingSearch.value = false;
}

async function removeSavedSearch(savedSearch: SavedSearch): Promise<void> {
  deletingSearchId.value = savedSearch.id;
  savedSearchActionError.value = '';
  const result = await deleteSavedSearch(savedSearch.id, authStore.tokenType, authStore.accessToken);
  if (result.ok) savedSearches.value = savedSearches.value.filter((item) => item.id !== savedSearch.id);
  else savedSearchActionError.value = result.message;
  deletingSearchId.value = '';
}

async function loadSavedSearches(): Promise<void> {
  savedSearchesLoading.value = true;
  savedSearchesError.value = '';
  const result = await fetchSavedSearches(authStore.tokenType, authStore.accessToken);
  if (result.ok) savedSearches.value = result.savedSearches;
  else savedSearchesError.value = result.message;
  savedSearchesLoading.value = false;
}

function retry(): void {
  void loadSearch();
}

watch(
  () => [route.query.q, route.query.page, route.query.cookbook_id, route.query.tag, route.query.ingredient, route.query.max_prep_time, route.query.max_cook_time, route.query.favorites, route.query.min_rating, route.query.sort],
  () => {
    search.value = queryString(route.query.q);
    cookbookId.value = queryString(route.query.cookbook_id);
    tag.value = queryString(route.query.tag);
    ingredient.value = queryString(route.query.ingredient);
    maxPrepTime.value = queryNumber(route.query.max_prep_time);
    maxCookTime.value = queryNumber(route.query.max_cook_time);
    favorites.value = queryBoolean(route.query.favorites);
    minRating.value = queryNumber(route.query.min_rating);
    ratingSort.value = queryString(route.query.sort);
    scheduleLoad();
  },
  { immediate: true },
);

onMounted(async () => {
  cookbooksLoading.value = true;
  cookbooksError.value = '';
  const cookbooksPromise = fetchCookbooks(authStore.tokenType, authStore.accessToken);
  const savedSearchesPromise = loadSavedSearches();
  const result = await cookbooksPromise;
  if (result.ok) cookbooks.value = result.data;
  else cookbooksError.value = result.message;
  cookbooksLoading.value = false;
  await savedSearchesPromise;
});

function retryCookbooks(): void {
  void (async () => {
    cookbooksLoading.value = true;
    cookbooksError.value = '';
    const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
    if (result.ok) cookbooks.value = result.data;
    else cookbooksError.value = result.message;
    cookbooksLoading.value = false;
  })();
}

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
        <div>
          <h3 id="filters-title">Filtres</h3>
          <p class="filter-summary">Affinez les résultats par cookbook, favoris, tags, ingrédients ou durée.</p>
        </div>
        <div class="filter-actions">
          <button type="button" class="quick-filter-button" :class="{ active: favorites }" :aria-pressed="favorites" :disabled="isLoading" @click="favorites = !favorites; syncFilters()">Favoris</button>
          <button type="button" class="advanced-filter-button" :aria-expanded="isFilterDrawerOpen" aria-controls="advanced-filters" @click="isFilterDrawerOpen = true">Tous les filtres</button>
        <button type="button" class="reset-button" :disabled="isLoading || !hasFilters" @click="resetFilters">Réinitialiser</button>
      </div>
        </div>
      <p v-if="cookbooksLoading" class="filter-status" role="status">Chargement des cookbooks...</p>
      <p v-else-if="cookbooksError" class="filter-status filter-error" role="alert">
        {{ cookbooksError }}
        <button type="button" @click="retryCookbooks">Réessayer</button>
      </p>
      <div v-show="isFilterDrawerOpen" id="advanced-filters" ref="filterDrawer" class="filters-grid filter-drawer" role="dialog" aria-modal="true" aria-labelledby="advanced-filters-title" tabindex="-1">
        <div class="drawer-heading">
          <h4 id="advanced-filters-title">Filtres avancés</h4>
          <button type="button" class="drawer-close" aria-label="Fermer les filtres avancés" @click="closeFilterDrawer">Fermer</button>
        </div>
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
        <label>
          Note minimale
          <select v-model="minRating" :disabled="isLoading" @change="syncFilters">
            <option value="">Toutes les notes</option>
            <option value="4">4/5 et plus</option>
            <option value="3">3/5 et plus</option>
            <option value="2">2/5 et plus</option>
            <option value="1">Au moins une note</option>
          </select>
        </label>
        <label>
          Trier par note
          <select v-model="ratingSort" :disabled="isLoading" @change="syncFilters">
            <option value="">Plus récentes</option>
            <option value="rating_desc">Note décroissante</option>
            <option value="rating_asc">Note croissante</option>
          </select>
        </label>
      </div>
    </section>

    <section v-if="activeFilterChips.length" class="active-filters" aria-labelledby="active-filters-title">
      <div class="active-filters-heading">
        <h3 id="active-filters-title">Filtres actifs</h3>
        <button type="button" class="reset-button" :disabled="isLoading" @click="resetFilters">Réinitialiser</button>
      </div>
      <ul class="filter-chips">
        <li v-for="chip in activeFilterChips" :key="chip.key" class="filter-chip">
          <span>{{ chip.label }}</span>
          <button type="button" :aria-label="`Retirer ${chip.label}`" @click="clearFilter(chip.key)">×</button>
        </li>
      </ul>
    </section>

    <section class="saved-searches-panel" aria-labelledby="saved-searches-title">
      <div class="filters-heading">
        <h3 id="saved-searches-title">Recherches sauvegardées</h3>
        <span v-if="savedSearchesLoading" class="filter-status" role="status">Chargement...</span>
      </div>
      <form class="save-search-form" @submit.prevent="saveCurrentSearch">
        <label for="saved-search-name">Nom de la recherche</label>
        <div class="save-search-controls">
          <input id="saved-search-name" v-model="savedSearchName" type="text" maxlength="100" placeholder="Ex. Dîners rapides" :disabled="savingSearch" />
          <button type="submit" class="save-button" :disabled="savingSearch">{{ savingSearch ? 'Sauvegarde...' : 'Sauvegarder' }}</button>
        </div>
      </form>
      <p v-if="savedSearchesError" class="filter-status filter-error" role="alert">{{ savedSearchesError }} <button type="button" @click="loadSavedSearches">Réessayer</button></p>
      <p v-if="savedSearchActionError" class="filter-status filter-error" role="alert">{{ savedSearchActionError }}</p>
      <ul v-if="savedSearches.length" class="saved-search-list">
        <li v-for="savedSearch in savedSearches" :key="savedSearch.id">
          <button type="button" class="saved-search-load" @click="applySavedSearch(savedSearch)">{{ savedSearch.name }}</button>
          <button type="button" class="saved-search-delete" :disabled="deletingSearchId === savedSearch.id" :aria-label="`Supprimer ${savedSearch.name}`" @click="removeSavedSearch(savedSearch)">
            {{ deletingSearchId === savedSearch.id ? 'Suppression...' : 'Supprimer' }}
          </button>
        </li>
      </ul>
      <p v-else-if="!savedSearchesLoading && !savedSearchesError" class="filter-status">Aucune recherche sauvegardée.</p>
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
.search-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.page-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.secondary-link { padding: .65rem .8rem; border: 1px solid #395330; border-radius: .6rem; color: #395330; font-weight: 700; text-decoration: none; }
.recipe-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
.filters-panel { margin-top: 1.5rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; background: rgba(247, 251, 243, .65); }
.filter-summary { margin: .35rem 0 0; color: #50634d; font-size: .9rem; font-weight: 400; }
.filter-actions { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; }
.quick-filter-button, .advanced-filter-button { padding: .45rem .65rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.quick-filter-button.active { background: #395330; color: #fffdf8; }
.filter-drawer { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 1rem; padding: 1rem; border: 1px solid #b9c5af; border-radius: .7rem; background: #fffdf8; }
.drawer-heading { display: flex; align-items: center; justify-content: space-between; grid-column: 1 / -1; gap: 1rem; }
.drawer-heading h4 { margin: 0; color: #243127; }
.drawer-close { padding: .45rem .65rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.active-filters { margin-top: 1rem; }
.active-filters-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.active-filters-heading h3 { margin: 0; color: #243127; font-size: 1rem; }
.filter-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin: .7rem 0 0; padding: 0; list-style: none; }
.filter-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .5rem .35rem .65rem; border: 1px solid #b9c5af; border-radius: 999px; background: #edf4e8; color: #243127; font-size: .88rem; }
.filter-chip button { display: inline-grid; width: 1.35rem; height: 1.35rem; place-items: center; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #243127; font: inherit; font-weight: 700; cursor: pointer; }
.filter-chip button:hover, .filter-chip button:focus-visible { background: #d6dfd0; }
.saved-searches-panel { margin-top: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; background: rgba(247, 251, 243, .65); }
.filters-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.filters-heading h3 { margin: 0; color: #243127; }
.reset-button { padding: .45rem .65rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.reset-button:disabled { cursor: not-allowed; opacity: .45; }
.filters-grid { display: grid; gap: .8rem; }
.filters-grid label { display: grid; gap: .35rem; color: #395330; font-size: .9rem; font-weight: 700; }
.filters-grid input:not([type="checkbox"]), .filters-grid select { width: 100%; box-sizing: border-box; padding: .65rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; color: #243127; font: inherit; font-weight: 400; }
.checkbox-filter { display: flex !important; align-items: center; grid-template-columns: auto 1fr; gap: .55rem !important; align-self: end; min-height: 2.4rem; }
.checkbox-filter input { width: 1.1rem; height: 1.1rem; accent-color: #395330; }
.filter-status { margin: .8rem 0 0; color: #50634d; font-size: .9rem; }
.filter-error { color: #8f1e1e; }
.save-search-form { display: grid; gap: .35rem; margin-top: 1rem; color: #395330; font-size: .9rem; font-weight: 700; }
.save-search-controls { display: flex; gap: .6rem; }
.save-search-controls input { min-width: 0; flex: 1; padding: .65rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; color: #243127; font: inherit; font-weight: 400; }
.save-button, .saved-search-delete { padding: .55rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.save-button:disabled, .saved-search-delete:disabled { cursor: wait; opacity: .6; }
.saved-search-list { display: grid; gap: .5rem; margin: 1rem 0 0; padding: 0; list-style: none; }
.saved-search-list li { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .55rem .65rem; border: 1px solid #d6dfd0; border-radius: .5rem; background: #fffdf8; }
.saved-search-load { padding: 0; border: 0; background: transparent; color: #395330; font: inherit; font-weight: 700; text-align: left; cursor: pointer; }
.saved-search-delete { border-color: #8f1e1e; background: transparent; color: #8f1e1e; }
.result-count { margin: 1.5rem 0 0; color: #50634d; font-size: .9rem; }
.state-message, .empty-state { margin-top: 1.5rem; padding: 1.25rem; border-radius: .8rem; }
.state-message { color: #50634d; }
.error-summary { color: #8f1e1e; background: #fff0ee; }
.error-summary button { padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.empty-state { border: 1px dashed #b9c5af; color: #50634d; }
.empty-state h3 { margin-top: 0; color: #243127; }
@media (max-width: 72rem) { .recipe-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 58rem) { .recipe-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 46rem) { .recipe-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 38rem) { .page-heading { align-items: flex-start; flex-direction: column; } .filter-actions { justify-content: start; } .filter-drawer { grid-template-columns: 1fr; } .recipe-grid { grid-template-columns: 1fr; } }
</style>
