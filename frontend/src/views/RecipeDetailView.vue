<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import AddToPlanningModal from '@/components/AddToPlanningModal.vue';
import Avatar from '@/components/Avatar.vue';
import RecipeCommentsSection from '@/components/RecipeCommentsSection.vue';
import RecipeFavoriteButton from '@/components/RecipeFavoriteButton.vue';
import RecipeRating from '@/components/RecipeRating.vue';
import { useAuthStore } from '@/stores/auth';
import { useRealtimeStore } from '@/stores/realtime';
import { addRecipeToCookbook, fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { deleteRecipe, duplicateRecipe, fetchRecipe, type Recipe } from '@/utils/recipes';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const realtimeStore = useRealtimeStore();
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
let subscribedRecipeCookbookId: string | null = null;
const isAuthor = computed(() => recipe.value?.author?.id === authStore.user?.id);
const editableCookbooks = computed(() => cookbooks.value.filter((item) => item.member_role === 'owner' || item.member_role === 'editor'));
const secondaryActions = ref<HTMLDetailsElement | null>(null);
const totalTime = computed(() => {
  const current = recipe.value;
  if (!current || current.prep_time_minutes === null || current.cook_time_minutes === null) return null;
  return current.prep_time_minutes + current.cook_time_minutes + (current.rest_time_minutes ?? 0);
});

function openPlanningModal(): void { planningSuccessMessage.value = ''; isPlanningModalVisible.value = true; }
function closePlanningModal(): void { isPlanningModalVisible.value = false; }
function handlePlanningAdded(): void { isPlanningModalVisible.value = false; planningSuccessMessage.value = 'La recette a été ajoutée au planning.'; }
function updatePersonalRating(value: number | null): void { if (recipe.value) recipe.value.personal_rating = value; }
function scrollToSection(id: string): void { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }

async function openCookbookPicker(): Promise<void> {
  isCookbookPickerVisible.value = true;
  cookbookError.value = '';
  await nextTick();
  if (secondaryActions.value) {
    secondaryActions.value.open = true;
    if (typeof secondaryActions.value.scrollIntoView === 'function') {
      secondaryActions.value.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
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
  if (result.ok) {
    recipe.value = result.recipe;
    const nextCookbookId = result.recipe.cookbook_id ?? null;
    if (subscribedRecipeCookbookId !== null && subscribedRecipeCookbookId !== nextCookbookId) {
      realtimeStore.unsubscribeCookbook(subscribedRecipeCookbookId);
    }
    subscribedRecipeCookbookId = nextCookbookId;
    if (nextCookbookId !== null) realtimeStore.subscribeCookbook(nextCookbookId);
  } else {
    errorMessage.value = result.message;
  }
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

watch(() => String(route.params.id), () => { void loadRecipe(); }, { immediate: true });
onBeforeUnmount(() => {
  if (subscribedRecipeCookbookId !== null) realtimeStore.unsubscribeCookbook(subscribedRecipeCookbookId);
});
</script>

<template>
  <main class="detail-page">
    <p v-if="isLoading" class="state-message" role="status">Chargement de la recette...</p>
    <section v-else-if="errorMessage" class="state-message error-summary" role="alert">
      {{ errorMessage }}
      <button type="button" @click="loadRecipe">Réessayer</button>
    </section>
    <template v-else-if="recipe">
      <aside class="recipe-action-panel" aria-label="Actions de la recette">
        <RouterLink class="action-back" :to="{ name: 'recipes' }">Retour aux recettes</RouterLink>
        <div class="action-list">
          <RecipeFavoriteButton :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" />
          <button type="button" class="action-button action-primary" @click="openPlanningModal">Ajouter au planning</button>
          <button type="button" class="action-button" @click="openCookbookPicker">Ajouter à un cookbook</button>
          <button type="button" class="action-button" @click="scrollToSection('comments-section')">Commenter</button>
          <button type="button" class="action-button" @click="scrollToSection('rating-section')">Évaluer</button>
          <RouterLink v-if="isAuthor" class="action-button action-link" :to="{ name: 'recipe-edit', params: { id: recipe.id } }">Modifier</RouterLink>
          <button v-if="isAuthor" type="button" class="action-button action-danger" :disabled="isDeleting" @click="openDeleteConfirmation">Supprimer</button>
        </div>
      </aside>
      <article class="recipe-detail">
      <img v-if="recipe.image_url" class="recipe-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
      <img v-else class="recipe-image recipe-image-placeholder" src="@/assets/recipe-no-image.svg" alt="Aucune photo pour cette recette" />

      <header class="recipe-header">
        <div>
          <p class="kicker">Recette</p>
          <h1>{{ recipe.title }}</h1>
          <p v-if="recipe.author" class="author-line">
            <Avatar :src="recipe.author.avatar_url ?? null" :name="recipe.author.name" size="small" />
            <span>Par {{ recipe.author.name }}</span>
          </p>
        </div>
      </header>

      <p v-if="planningSuccessMessage" class="planning-success" role="status">{{ planningSuccessMessage }}</p>
      <AddToPlanningModal v-if="isPlanningModalVisible" :recipe="recipe" @close="closePlanningModal" @added="handlePlanningAdded" />

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

      <section id="rating-section" class="rating-section" aria-labelledby="rating-heading">
        <h2 id="rating-heading">Notation</h2>
        <p class="recipe-average-rating" aria-label="Note moyenne">★ {{ (recipe.average_rating ?? 0).toFixed(1) }}/5 ({{ recipe.rating_count ?? 0 }} vote{{ (recipe.rating_count ?? 0) > 1 ? 's' : '' }})</p>
        <RecipeRating :recipe-id="recipe.id" :personal-rating="recipe.personal_rating ?? null" @update:personal-rating="updatePersonalRating" />
      </section>

      <details ref="secondaryActions" class="secondary-actions">
        <summary>Historique et autres actions</summary>
        <div class="secondary-actions-content">
          <RouterLink class="history-link prominent-secondary-link" :to="{ name: 'recipe-history', params: { id: recipe.id } }">Voir l’historique des modifications</RouterLink>
          <button v-if="!isCookbookPickerVisible" type="button" class="cookbook-button" @click="openCookbookPicker">Ajouter à un cookbook</button>
          <form v-else class="cookbook-picker" @submit.prevent="addToSelectedCookbook">
            <label for="recipe-cookbook">Choisir un cookbook</label>
            <select id="recipe-cookbook" v-model="selectedCookbookId" :disabled="isLoadingCookbooks || isAddingToCookbook">
              <option value="">Choisir un cookbook</option>
              <option v-for="cookbook in editableCookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
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
              <option v-for="cookbook in editableCookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
            </select>
            <label for="duplicate-confirmation">Pour confirmer, saisissez « {{ recipe.title }} »</label>
            <input id="duplicate-confirmation" v-model="duplicateConfirmation" type="text" :disabled="isDuplicating" autocomplete="off" />
            <p v-if="isLoadingCookbooks" class="muted">Chargement des destinations...</p>
            <p v-if="duplicateError" class="delete-error" role="alert">{{ duplicateError }}</p>
            <div class="duplicate-actions"><button type="submit" :disabled="isDuplicating || duplicateConfirmation.trim() !== recipe.title">{{ isDuplicating ? 'Duplication...' : 'Confirmer la duplication' }}</button><button type="button" class="cancel-button" :disabled="isDuplicating" @click="closeDuplicatePicker">Annuler</button></div>
          </form>

          <section v-if="isDeleteConfirmationVisible" class="delete-confirmation" aria-labelledby="delete-recipe-heading">
            <h3 id="delete-recipe-heading">Supprimer cette recette ?</h3>
            <p>Cette action supprimera la recette et ses ingrédients, étapes et associations de tags.</p>
            <p v-if="deleteError" class="delete-error" role="alert">{{ deleteError }}</p>
            <div class="delete-actions"><button type="button" class="delete-button" :disabled="isDeleting" @click="confirmDelete">{{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}</button><button type="button" class="cancel-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button></div>
          </section>
        </div>
      </details>

      <section id="comments-section" class="comments-section">
        <RecipeCommentsSection :recipe-id="recipe.id" :token-type="authStore.tokenType" :access-token="authStore.accessToken" :current-user-id="authStore.user?.id ?? null" />
      </section>
    </article>
    </template>
  </main>
</template>

<style scoped>
.detail-page { width: 100%; max-width: 72rem; margin: 0 auto; }
.back-link { color: var(--color-primary); font-weight: 700; text-underline-offset: .18em; }
.state-message { margin-top: 2rem; color: var(--color-text-secondary); }
.error-summary { padding: 1rem; border: 1px solid var(--color-danger); border-radius: var(--radius-lg); color: var(--color-danger); background: var(--color-danger-soft); }
.error-summary button { display: block; margin-top: .75rem; padding: .5rem .7rem; border: 1px solid var(--color-danger); border-radius: var(--radius-md); background: transparent; color: var(--color-danger); font: inherit; font-weight: 700; cursor: pointer; }
.recipe-detail { margin-top: 1.5rem; padding: clamp(1.15rem, 4vw, 3rem); border: 1px solid var(--color-border); border-radius: var(--radius-xl); background: var(--color-surface); color: var(--color-text); box-shadow: var(--shadow-sm); }
.recipe-header { display: block; width: 100%; }
.kicker { margin: 0 0 .5rem; color: var(--color-primary); font-size: .75rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
h1 { width: 100%; margin: 0; font-size: clamp(2.25rem, 6vw, 4.5rem); line-height: 1.02; letter-spacing: -.035em; }
.author-line { display: flex; align-items: center; gap: .55rem; margin: .8rem 0 0; color: var(--color-text-secondary); font-size: .95rem; }
.recipe-action-panel { display: flex; align-items: center; gap: .65rem; margin: 1.25rem 0 1.5rem; padding: .35rem; border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface-subtle); }
.action-back { flex: 0 0 auto; padding: .55rem .7rem; border-right: 1px solid var(--color-border); color: var(--color-primary); font-size: .8rem; font-weight: 700; text-decoration: none; }
.action-list { display: flex; flex: 1; flex-wrap: wrap; align-items: center; gap: .35rem; }
.action-list :deep(.favorite-control) { display: block; }
.action-list :deep(.favorite-button), .action-button { display: flex; width: auto; align-items: center; justify-content: flex-start; min-height: 2.5rem; padding: .55rem .65rem; border: 1px solid transparent; border-radius: var(--radius-md); background: transparent; color: var(--color-text-secondary); font: inherit; font-size: .875rem; font-weight: 700; text-align: left; text-decoration: none; cursor: pointer; transition: background-color .16s ease, border-color .16s ease, color .16s ease; }
.action-list :deep(.favorite-button:hover), .action-list :deep(.favorite-button:focus-visible), .action-list :deep(.favorite-button.active), .action-button:hover, .action-button:focus-visible { border-color: var(--color-border); background: var(--color-surface-subtle); color: var(--color-primary); }
.action-button.action-primary { border-color: transparent; background: transparent; color: var(--color-text-secondary); }
.action-button.action-primary:hover, .action-button.action-primary:focus-visible { border-color: var(--color-border); background: var(--color-surface-subtle); color: var(--color-primary); }
.action-button.action-danger { color: var(--color-danger); }
.action-button.action-danger:hover, .action-button.action-danger:focus-visible { border-color: var(--color-danger); background: var(--color-danger-soft); }
.comments-section, #rating-section { scroll-margin-top: 1.5rem; }
.recipe-image { display: block; width: 100%; max-height: 38rem; margin: 2rem 0 1.5rem; object-fit: cover; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.tags { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1.1rem; padding-top: .9rem; border-top: 1px solid var(--color-border); }
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
@media (max-width: 48rem) { .recipe-action-panel { align-items: stretch; flex-direction: column; }.action-back { width: 100%; border-right: 0; border-bottom: 1px solid var(--color-border); }.action-list { display: grid; width: 100%; }.action-list :deep(.favorite-button), .action-button { width: 100%; } }
@media (max-width: 34rem) { .recipe-image { max-height: 20rem; } .recipe-facts span + span { padding-left: 0; border-left: 0; } }
</style>
