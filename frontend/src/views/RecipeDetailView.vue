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
.detail-page { width: 100%; max-width: 72rem; margin: 0 auto; }
.back-link { color: var(--color-primary); font-weight: 700; text-underline-offset: .18em; }
.state-message { margin-top: 2rem; color: var(--color-text-secondary); }
.error-summary { padding: 1rem; border: 1px solid var(--color-danger); border-radius: var(--radius-lg); color: var(--color-danger); background: var(--color-danger-soft); }
.error-summary button { display: block; margin-top: .75rem; padding: .5rem .7rem; border: 1px solid var(--color-danger); border-radius: var(--radius-md); background: transparent; color: var(--color-danger); font: inherit; font-weight: 700; cursor: pointer; }
.recipe-detail { margin-top: 1.5rem; padding: clamp(1.15rem, 4vw, 3rem); border: 1px solid var(--color-border); border-radius: var(--radius-xl); background: var(--color-surface); color: var(--color-text); box-shadow: var(--shadow-sm); }
.recipe-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 2rem; }
.kicker { margin: 0 0 .5rem; color: var(--color-primary); font-size: .75rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
h1 { max-width: 42rem; margin: 0; font-size: clamp(2.25rem, 6vw, 4.5rem); line-height: 1.02; letter-spacing: -.035em; }
.author-line { margin: .8rem 0 0; color: var(--color-text-secondary); font-size: .95rem; }
.primary-actions { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; }
.primary-actions button, .primary-edit { display: inline-flex; align-items: center; justify-content: center; min-height: 2.5rem; padding: .55rem .8rem; border-radius: var(--radius-md); font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; transition: background-color .16s ease, border-color .16s ease, color .16s ease; }
.planning-button { border: 1px solid var(--color-primary); background: var(--color-primary); color: #fffdf9; }
.planning-button:hover, .planning-button:focus-visible { border-color: var(--color-primary-hover); background: var(--color-primary-hover); }
.primary-edit { border: 1px solid var(--color-border-strong); background: var(--color-surface); color: var(--color-primary); }
.primary-edit:hover, .primary-edit:focus-visible { border-color: var(--color-primary); background: var(--color-primary-soft); }
.recipe-image { display: block; width: 100%; max-height: 38rem; margin: 2rem 0 1.5rem; object-fit: cover; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.tag { padding: .25rem .55rem; border: 1px solid var(--color-border); border-radius: var(--radius-pill); background: var(--color-surface-subtle); color: var(--color-text-secondary); font-size: .78rem; }
.description, .source, .muted { color: var(--color-text-secondary); line-height: 1.65; }
.description { max-width: 58rem; margin: 1rem 0 0; font-size: 1.05rem; }
.recipe-facts { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; margin: 1.35rem 0 0; padding: .9rem 0; border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: .875rem; }
.recipe-facts span + span { padding-left: 1.25rem; border-left: 1px solid var(--color-border); }
.source a { color: var(--color-primary); overflow-wrap: anywhere; }
.detail-section, .rating-section, .secondary-actions { margin-top: 2.75rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border); }
h2 { margin: 0 0 1rem; font-size: clamp(1.5rem, 3vw, 2rem); letter-spacing: -.02em; }
.ingredient-list, .step-list { display: grid; gap: .8rem; margin: 0; padding-left: 1.7rem; color: var(--color-text); font-size: 1.05rem; line-height: 1.65; }
.ingredient-list li, .step-list li { padding-left: .35rem; }
.ingredient-list li::marker, .step-list li::marker { color: var(--color-primary); font-weight: 800; }
.optional { color: var(--color-text-muted); font-size: .9rem; }
.step-list { gap: 1.25rem; }
.step-list li { padding: .2rem 0 .2rem .55rem; }
.step-list p { margin: 0; }
.step-list small { display: inline-block; margin-top: .35rem; color: var(--color-text-muted); font-size: .85rem; }
.recipe-average-rating { margin: .5rem 0 1rem; color: var(--color-accent); font-weight: 700; }
.planning-success { color: var(--color-success); font-weight: 700; }
.secondary-actions summary { color: var(--color-primary); font-weight: 700; cursor: pointer; }
.secondary-actions-content { display: grid; gap: .7rem; margin-top: 1rem; }
.history-link { width: fit-content; color: var(--color-primary); font-weight: 700; }
.prominent-secondary-link { padding-bottom: .7rem; border-bottom: 1px solid var(--color-border); }
.cookbook-button, .duplicate-button, .delete-button, .cancel-button { width: fit-content; padding: .55rem .75rem; border-radius: var(--radius-md); font: inherit; font-weight: 700; cursor: pointer; }
.cookbook-button { border: 1px solid var(--color-primary); background: var(--color-primary); color: #fffdf9; }
.duplicate-button, .cancel-button { border: 1px solid var(--color-border-strong); background: var(--color-surface); color: var(--color-primary); }
.duplicate-button:hover, .cancel-button:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
.delete-button { border: 1px solid var(--color-danger); background: var(--color-danger); color: #fff; }
.cookbook-picker, .duplicate-picker { display: grid; gap: .55rem; max-width: 26rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface-subtle); }
.cookbook-picker select, .duplicate-picker select, .duplicate-picker input { padding: .6rem; }
.cookbook-picker-actions, .duplicate-actions, .delete-actions { display: flex; flex-wrap: wrap; gap: .6rem; }
.cookbook-picker-actions button, .duplicate-actions button:first-child { padding: .5rem .7rem; border: 1px solid var(--color-primary); border-radius: var(--radius-md); background: var(--color-primary); color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
.delete-confirmation { padding: 1rem; border: 1px solid var(--color-danger); border-radius: var(--radius-lg); background: var(--color-danger-soft); }
.delete-confirmation h3 { margin-top: 0; color: var(--color-danger); }
.delete-confirmation p { color: var(--color-text-secondary); line-height: 1.5; }
.delete-error { color: var(--color-danger) !important; font-weight: 700; }
button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 48rem) { .recipe-header { flex-direction: column; gap: 1.25rem; }.primary-actions { justify-content: start; }.primary-actions button, .primary-edit { flex: 1; } }
@media (max-width: 34rem) { .primary-actions { display: grid; width: 100%; }.primary-actions button, .primary-edit { width: 100%; }.recipe-image { max-height: 20rem; } .recipe-facts span + span { padding-left: 0; border-left: 0; } }
</style>
