<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import AddToPlanningModal from '@/components/AddToPlanningModal.vue';
import RecipeCommentsSection from '@/components/RecipeCommentsSection.vue';
import RecipeFavoriteButton from '@/components/RecipeFavoriteButton.vue';
import RecipeRating from '@/components/RecipeRating.vue';
import { useAuthStore } from '@/stores/auth';
import { addRecipeToCookbook, fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { deleteRecipe, duplicateRecipe, fetchRecipe, type Recipe } from '@/utils/recipes';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const recipe = ref<Recipe | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const isDeleteConfirmationVisible = ref(false);
const isDeleting = ref(false);
const deleteError = ref('');
const isCookbookPickerVisible = ref(false);
const cookbooks = ref<Cookbook[]>([]);
const selectedCookbookId = ref('');
const isLoadingCookbooks = ref(false);
const isAddingToCookbook = ref(false);
const cookbookError = ref('');
const isDuplicatePickerVisible = ref(false);
const duplicateDestination = ref('personal');
const duplicateConfirmation = ref('');
const isDuplicating = ref(false);
const duplicateError = ref('');
const isPlanningModalVisible = ref(false);
const planningSuccessMessage = ref('');
const totalTime = computed(() => {
  const current = recipe.value;
  if (!current || current.prep_time_minutes === null || current.cook_time_minutes === null) return null;
  return current.prep_time_minutes + current.cook_time_minutes + (current.rest_time_minutes ?? 0);
});

function openPlanningModal(): void { planningSuccessMessage.value = ''; isPlanningModalVisible.value = true; }
function closePlanningModal(): void { isPlanningModalVisible.value = false; }
function handlePlanningAdded(): void { isPlanningModalVisible.value = false; planningSuccessMessage.value = 'La recette a été ajoutée au planning.'; }
function updatePersonalRating(value: number | null): void { if (recipe.value) recipe.value.personal_rating = value; }

async function openCookbookPicker(): Promise<void> {
  isCookbookPickerVisible.value = true;
  cookbookError.value = '';
  if (cookbooks.value.length > 0) return;
  isLoadingCookbooks.value = true;
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) cookbooks.value = result.data;
  else cookbookError.value = result.message;
  isLoadingCookbooks.value = false;
}

async function addToSelectedCookbook(): Promise<void> {
  if (!recipe.value || selectedCookbookId.value === '') return;
  isAddingToCookbook.value = true;
  cookbookError.value = '';
  const result = await addRecipeToCookbook(selectedCookbookId.value, recipe.value.id, authStore.tokenType, authStore.accessToken);
  if (result.ok) { selectedCookbookId.value = ''; isCookbookPickerVisible.value = false; }
  else cookbookError.value = result.message;
  isAddingToCookbook.value = false;
}

async function loadCookbooks(): Promise<void> {
  isLoadingCookbooks.value = true;
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) cookbooks.value = result.data;
  else duplicateError.value = result.message;
  isLoadingCookbooks.value = false;
}

function openDuplicatePicker(): void {
  duplicateError.value = '';
  duplicateConfirmation.value = '';
  duplicateDestination.value = 'personal';
  isDuplicatePickerVisible.value = true;
  if (cookbooks.value.length === 0) void loadCookbooks();
}
function closeDuplicatePicker(): void {
  if (isDuplicating.value) return;
  isDuplicatePickerVisible.value = false;
  duplicateError.value = '';
}
async function confirmDuplicate(): Promise<void> {
  if (!recipe.value) return;
  if (duplicateConfirmation.value.trim() !== recipe.value.title) {
    duplicateError.value = 'Saisissez exactement le titre de la recette pour confirmer.';
    return;
  }
  isDuplicating.value = true;
  duplicateError.value = '';
  const result = await duplicateRecipe(recipe.value.id, duplicateConfirmation.value, duplicateDestination.value === 'personal' ? null : duplicateDestination.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) { await router.push({ name: 'recipe-detail', params: { id: result.recipe.id } }); return; }
  duplicateError.value = result.message;
  isDuplicating.value = false;
}

async function loadRecipe(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipe(String(route.params.id), authStore.tokenType, authStore.accessToken);
  if (result.ok) recipe.value = result.recipe;
  else errorMessage.value = result.message;
  isLoading.value = false;
}

function ingredientLabel(quantity: number | null, unit: string): string {
  if (quantity === null && unit === '') return '';
  return [quantity, unit].filter((value) => value !== null && value !== '').join(' ');
}
function openDeleteConfirmation(): void { deleteError.value = ''; isDeleteConfirmationVisible.value = true; }
function cancelDelete(): void {
  if (isDeleting.value) return;
  isDeleteConfirmationVisible.value = false;
  deleteError.value = '';
}
async function confirmDelete(): Promise<void> {
  if (!recipe.value) return;
  deleteError.value = '';
  isDeleting.value = true;
  const result = await deleteRecipe(recipe.value.id, authStore.tokenType, authStore.accessToken);
  if (result.ok) { await router.push({ name: 'recipes' }); return; }
  deleteError.value = result.message;
  isDeleting.value = false;
}

onMounted(() => { void loadRecipe(); });
</script>

<template>
  <main class="detail-page">
    <RouterLink class="back-link" :to="{ name: 'recipes' }">Retour à mes recettes</RouterLink>
    <p v-if="isLoading" class="state-message" role="status">Chargement de la recette...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">
      {{ errorMessage }}
      <button type="button" @click="loadRecipe">Réessayer</button>
    </section>
    <article v-else-if="recipe" class="recipe-detail">
      <header class="recipe-header">
        <div>
          <p class="kicker">Recette</p>
          <h1>{{ recipe.title }}</h1>
          <p v-if="recipe.author" class="author-line">Par {{ recipe.author.name }}</p>
        </div>
        <div class="primary-actions" aria-label="Actions principales">
          <RecipeFavoriteButton :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" />
          <button type="button" class="planning-button" @click="openPlanningModal">Ajouter au planning</button>
          <RouterLink class="edit-link primary-edit" :to="{ name: 'recipe-edit', params: { id: recipe.id } }">Modifier</RouterLink>
        </div>
      </header>

      <p v-if="planningSuccessMessage" class="planning-success" role="status">{{ planningSuccessMessage }}</p>
      <AddToPlanningModal v-if="isPlanningModalVisible" :recipe="recipe" @close="closePlanningModal" @added="handlePlanningAdded" />

      <img v-if="recipe.image_url" class="recipe-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
      <div v-if="recipe.tags?.length" class="tags" aria-label="Tags">
        <span v-for="tag in recipe.tags" :key="tag.id" class="tag">{{ tag.name }}</span>
      </div>
      <p v-if="recipe.description" class="description">{{ recipe.description }}</p>

      <section class="recipe-facts" aria-label="Informations principales">
        <span v-if="recipe.prep_time_minutes !== null">Préparation : {{ recipe.prep_time_minutes }} min</span>
        <span v-if="recipe.cook_time_minutes !== null">Cuisson : {{ recipe.cook_time_minutes }} min</span>
        <span v-if="totalTime !== null">Total : {{ totalTime }} min</span>
        <span v-if="recipe.servings !== null">Portions : {{ recipe.servings }}</span>
      </section>

      <section class="detail-section" aria-labelledby="ingredients-heading">
        <h2 id="ingredients-heading">Ingrédients</h2>
        <p v-if="!recipe.ingredients?.length" class="muted">Aucun ingrédient renseigné.</p>
        <ul v-else class="ingredient-list">
          <li v-for="ingredient in recipe.ingredients" :key="ingredient.position">
            <strong>{{ ingredientLabel(ingredient.quantity, ingredient.unit) }}</strong> {{ ingredient.name }}
            <span v-if="ingredient.preparation" class="muted"> — {{ ingredient.preparation }}</span>
            <span v-if="ingredient.is_optional" class="optional"> (facultatif)</span>
          </li>
        </ul>
      </section>

      <section class="detail-section" aria-labelledby="steps-heading">
        <h2 id="steps-heading">Étapes</h2>
        <p v-if="!recipe.steps?.length" class="muted">Aucune étape renseignée.</p>
        <ol v-else class="step-list">
          <li v-for="step in recipe.steps" :key="step.position">
            <p>{{ step.instruction }}</p>
            <small v-if="step.duration_minutes !== null">{{ step.duration_minutes }} min</small>
          </li>
        </ol>
      </section>

      <p v-if="recipe.source" class="source">Source : <a :href="recipe.source" target="_blank" rel="noreferrer">{{ recipe.source }}</a></p>

      <section class="rating-section" aria-labelledby="rating-heading">
        <h2 id="rating-heading">Notation</h2>
        <p class="recipe-average-rating" aria-label="Note moyenne">★ {{ (recipe.average_rating ?? 0).toFixed(1) }}/5 ({{ recipe.rating_count ?? 0 }} vote{{ (recipe.rating_count ?? 0) > 1 ? 's' : '' }})</p>
        <RecipeRating :recipe-id="recipe.id" :personal-rating="recipe.personal_rating ?? null" @update:personal-rating="updatePersonalRating" />
      </section>

      <details class="secondary-actions">
        <summary>Historique et autres actions</summary>
        <div class="secondary-actions-content">
          <RouterLink class="history-link prominent-secondary-link" :to="{ name: 'recipe-history', params: { id: recipe.id } }">Voir l’historique des modifications</RouterLink>
          <button v-if="!isCookbookPickerVisible" type="button" class="cookbook-button" @click="openCookbookPicker">Ajouter à un cookbook</button>
          <form v-else class="cookbook-picker" @submit.prevent="addToSelectedCookbook">
            <label for="recipe-cookbook">Choisir un cookbook</label>
            <select id="recipe-cookbook" v-model="selectedCookbookId" :disabled="isLoadingCookbooks || isAddingToCookbook">
              <option value="">Choisir un cookbook</option>
              <option v-for="cookbook in cookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
            </select>
            <div class="cookbook-picker-actions"><button type="submit" :disabled="isAddingToCookbook || selectedCookbookId === ''">{{ isAddingToCookbook ? 'Ajout...' : 'Ajouter' }}</button><button type="button" class="cancel-button" :disabled="isAddingToCookbook" @click="isCookbookPickerVisible = false">Annuler</button></div>
            <p v-if="isLoadingCookbooks" class="muted">Chargement des cookbooks...</p>
            <p v-if="cookbookError" class="delete-error" role="alert">{{ cookbookError }}</p>
          </form>

          <button v-if="!isDuplicatePickerVisible" type="button" class="duplicate-button" @click="openDuplicatePicker">Dupliquer la recette</button>
          <form v-else class="duplicate-picker" @submit.prevent="confirmDuplicate">
            <h3 id="duplicate-recipe-heading">Dupliquer cette recette</h3>
            <label for="duplicate-destination">Destination</label>
            <select id="duplicate-destination" v-model="duplicateDestination" :disabled="isLoadingCookbooks || isDuplicating">
              <option value="personal">Mes recettes</option>
              <option v-for="cookbook in cookbooks.filter((item) => item.member_role === 'owner' || item.member_role === 'editor')" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
            </select>
            <label for="duplicate-confirmation">Pour confirmer, saisissez « {{ recipe.title }} »</label>
            <input id="duplicate-confirmation" v-model="duplicateConfirmation" type="text" :disabled="isDuplicating" autocomplete="off" />
            <p v-if="isLoadingCookbooks" class="muted">Chargement des destinations...</p>
            <p v-if="duplicateError" class="delete-error" role="alert">{{ duplicateError }}</p>
            <div class="duplicate-actions"><button type="submit" :disabled="isDuplicating || duplicateConfirmation.trim() !== recipe.title">{{ isDuplicating ? 'Duplication...' : 'Confirmer la duplication' }}</button><button type="button" class="cancel-button" :disabled="isDuplicating" @click="closeDuplicatePicker">Annuler</button></div>
          </form>

          <button v-if="!isDeleteConfirmationVisible" type="button" class="delete-button" :disabled="isDeleting" @click="openDeleteConfirmation">Supprimer la recette</button>
          <section v-else class="delete-confirmation" aria-labelledby="delete-recipe-heading">
            <h3 id="delete-recipe-heading">Supprimer cette recette ?</h3>
            <p>Cette action supprimera la recette et ses ingrédients, étapes et associations de tags.</p>
            <p v-if="deleteError" class="delete-error" role="alert">{{ deleteError }}</p>
            <div class="delete-actions"><button type="button" class="delete-button" :disabled="isDeleting" @click="confirmDelete">{{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}</button><button type="button" class="cancel-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button></div>
          </section>
        </div>
      </details>

      <RecipeCommentsSection :recipe-id="recipe.id" :token-type="authStore.tokenType" :access-token="authStore.accessToken" :current-user-id="authStore.user?.id ?? null" />
    </article>
  </main>
</template>

<style scoped>
.detail-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.back-link { color: #395330; font-weight: 700; }
.state-message { margin-top: 2rem; color: #50634d; }
.error-summary { padding: 1rem; border-radius: .8rem; color: #8f1e1e; background: #fff0ee; }
.error-summary button { display: block; margin-top: .75rem; padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.recipe-detail { margin-top: 1.5rem; padding: clamp(1rem, 3vw, 2rem); border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.recipe-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1 { margin: 0; font-size: clamp(2rem, 5vw, 3.4rem); line-height: 1.05; }
.author-line { margin: .65rem 0 0; color: #50634d; }
.primary-actions { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; }
.primary-actions button, .primary-edit { display: inline-flex; align-items: center; justify-content: center; min-height: 2.5rem; padding: .55rem .75rem; border-radius: .5rem; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
.planning-button { border: 1px solid #395330; background: #395330; color: #fffdf8; }
.primary-edit { border: 1px solid #395330; color: #395330; }
.recipe-image { display: block; width: 100%; max-height: 32rem; margin: 1.5rem 0; object-fit: cover; border-radius: 1rem; }
.tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.tag { padding: .3rem .55rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: .85rem; }
.description, .source, .muted { color: #50634d; line-height: 1.6; }
.recipe-facts { display: flex; flex-wrap: wrap; gap: .5rem 1rem; margin: 1.2rem 0; color: #395330; font-weight: 700; }
.source a { color: #395330; overflow-wrap: anywhere; }
.detail-section, .rating-section, .secondary-actions { margin-top: 2rem; padding-top: 1.2rem; border-top: 1px solid rgba(86,112,79,.18); }
h2 { margin: 0 0 .8rem; font-size: 1.5rem; }
.ingredient-list, .step-list { display: grid; gap: .65rem; padding-left: 1.4rem; line-height: 1.5; }
.ingredient-list li::marker, .step-list li::marker { color: #6b7b57; font-weight: 700; }
.optional { color: #6b7b57; font-size: .9rem; }
.step-list p { margin: 0; }.step-list small { color: #50634d; }
.recipe-average-rating { margin: .5rem 0 1rem; color: #a46114; font-weight: 700; }
.planning-success { color: #395330; font-weight: 700; }
.secondary-actions summary { color: #395330; font-weight: 700; cursor: pointer; }
.secondary-actions-content { display: grid; gap: .7rem; margin-top: 1rem; }
.history-link { width: fit-content; color: #395330; font-weight: 700; }
.prominent-secondary-link { padding-bottom: .7rem; border-bottom: 1px solid rgba(86,112,79,.18); }
.cookbook-button, .duplicate-button, .delete-button, .cancel-button { width: fit-content; padding: .55rem .75rem; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.cookbook-button { border: 1px solid #395330; background: #395330; color: #fffdf8; }.duplicate-button, .cancel-button { border: 1px solid #395330; background: transparent; color: #395330; }.delete-button { border: 1px solid #8f1e1e; background: #8f1e1e; color: #fffdf8; }
.cookbook-picker, .duplicate-picker { display: grid; gap: .55rem; max-width: 26rem; padding: .9rem; border: 1px solid rgba(86,112,79,.18); border-radius: .7rem; }.cookbook-picker select, .duplicate-picker select, .duplicate-picker input { padding: .5rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }.cookbook-picker-actions, .duplicate-actions, .delete-actions { display: flex; flex-wrap: wrap; gap: .6rem; }.cookbook-picker-actions button, .duplicate-actions button:first-child { padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.delete-confirmation { padding: 1rem; border: 1px solid #e2b3ad; border-radius: .8rem; background: #fff8f6; }.delete-confirmation h3 { margin-top: 0; color: #8f1e1e; }.delete-confirmation p { color: #6d4140; line-height: 1.5; }.delete-error { color: #8f1e1e !important; font-weight: 700; }button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 48rem) { .recipe-header { flex-direction: column; }.primary-actions { justify-content: start; }.primary-actions button, .primary-edit { flex: 1; } }
@media (max-width: 34rem) { .primary-actions { display: grid; width: 100%; }.primary-actions button, .primary-edit { width: 100%; }.recipe-image { max-height: 20rem; } }
</style>
