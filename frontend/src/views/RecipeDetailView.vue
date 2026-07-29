<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import RecipeFavoriteButton from '@/components/RecipeFavoriteButton.vue';
import AddToPlanningModal from '@/components/AddToPlanningModal.vue';
import RecipeCommentsSection from '@/components/RecipeCommentsSection.vue';
import { useAuthStore } from '@/stores/auth';
import { addRecipeToCookbook, fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { deleteRecipe, fetchRecipe, type Recipe } from '@/utils/recipes';

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
const isPlanningModalVisible = ref(false);
const planningSuccessMessage = ref('');

function openPlanningModal(): void {
  planningSuccessMessage.value = '';
  isPlanningModalVisible.value = true;
}

function closePlanningModal(): void {
  isPlanningModalVisible.value = false;
}

function handlePlanningAdded(): void {
  isPlanningModalVisible.value = false;
  planningSuccessMessage.value = 'La recette a été ajoutée au planning.';
}

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
  const result = await addRecipeToCookbook(
    selectedCookbookId.value,
    recipe.value.id,
    authStore.tokenType,
    authStore.accessToken,
  );
  if (result.ok) {
    selectedCookbookId.value = '';
    isCookbookPickerVisible.value = false;
  } else {
    cookbookError.value = result.message;
  }
  isAddingToCookbook.value = false;
}

async function loadRecipe(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchRecipe(String(route.params.id), authStore.tokenType, authStore.accessToken);
  if (result.ok) recipe.value = result.recipe;
  else errorMessage.value = result.message;
  isLoading.value = false;
}

onMounted(() => { void loadRecipe(); });

function ingredientLabel(quantity: number | null, unit: string): string {
  if (quantity === null && unit === '') return '';
  return [quantity, unit].filter((value) => value !== null && value !== '').join(' ');
}

function openDeleteConfirmation(): void {
  deleteError.value = '';
  isDeleteConfirmationVisible.value = true;
}

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

  if (result.ok) {
    await router.push({ name: 'recipes' });
    return;
  }

  deleteError.value = result.message;
  isDeleting.value = false;
}
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
      <p class="kicker">Recette</p>
      <h2>{{ recipe.title }}</h2>
      <RecipeFavoriteButton :recipe-id="recipe.id" :is-favorite="recipe.is_favorite ?? false" />
      <button type="button" class="planning-button" @click="openPlanningModal">Ajouter au planning</button>
      <p v-if="planningSuccessMessage" class="planning-success" role="status">{{ planningSuccessMessage }}</p>
      <AddToPlanningModal v-if="isPlanningModalVisible" :recipe="recipe" @close="closePlanningModal" @added="handlePlanningAdded" />
      <button v-if="!isCookbookPickerVisible" type="button" class="cookbook-button" @click="openCookbookPicker">
        Ajouter à un cookbook
      </button>
      <form v-else class="cookbook-picker" @submit.prevent="addToSelectedCookbook">
        <label for="recipe-cookbook">Choisir un cookbook</label>
        <select id="recipe-cookbook" v-model="selectedCookbookId" :disabled="isLoadingCookbooks || isAddingToCookbook">
          <option value="">Choisir un cookbook</option>
          <option v-for="cookbook in cookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
        </select>
        <div class="cookbook-picker-actions">
          <button type="submit" :disabled="isAddingToCookbook || selectedCookbookId === ''">
            {{ isAddingToCookbook ? 'Ajout...' : 'Ajouter' }}
          </button>
          <button type="button" class="cancel-button" :disabled="isAddingToCookbook" @click="isCookbookPickerVisible = false">Annuler</button>
        </div>
        <p v-if="isLoadingCookbooks" class="muted">Chargement des cookbooks...</p>
        <p v-if="cookbookError" class="delete-error" role="alert">{{ cookbookError }}</p>
      </form>
      <img v-if="recipe.image_url" class="recipe-image" :src="recipe.image_url" :alt="'Photo de ' + recipe.title" />
      <RouterLink class="edit-link" :to="{ name: 'recipe-edit', params: { id: recipe.id } }">Modifier la recette</RouterLink>
      <button v-if="!isDeleteConfirmationVisible" type="button" class="delete-button" :disabled="isDeleting" @click="openDeleteConfirmation">
        Supprimer la recette
      </button>
      <section v-else class="delete-confirmation" aria-labelledby="delete-recipe-heading">
        <h3 id="delete-recipe-heading">Supprimer cette recette ?</h3>
        <p>Cette action supprimera la recette et ses ingrédients, étapes et associations de tags.</p>
        <p v-if="deleteError" class="delete-error" role="alert">{{ deleteError }}</p>
        <div class="delete-actions">
          <button type="button" class="delete-button" :disabled="isDeleting" @click="confirmDelete">
            {{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}
          </button>
          <button type="button" class="cancel-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button>
        </div>
      </section>
      <p v-if="recipe.author" class="author">Par {{ recipe.author.name }}</p>
      <p v-if="recipe.description" class="description">{{ recipe.description }}</p>
      <div class="meta">
        <span v-if="recipe.prep_time_minutes !== null">Préparation : {{ recipe.prep_time_minutes }} min</span>
        <span v-if="recipe.cook_time_minutes !== null">Cuisson : {{ recipe.cook_time_minutes }} min</span>
        <span v-if="recipe.servings !== null">Portions : {{ recipe.servings }}</span>
      </div>
      <div v-if="recipe.tags?.length" class="tags" aria-label="Tags">
        <span v-for="tag in recipe.tags" :key="tag.id" class="tag">{{ tag.name }}</span>
      </div>
      <p v-if="recipe.source" class="source">Source : <a :href="recipe.source" target="_blank" rel="noreferrer">{{ recipe.source }}</a></p>

      <section class="detail-section" aria-labelledby="ingredients-heading">
        <h3 id="ingredients-heading">Ingrédients</h3>
        <p v-if="!recipe.ingredients?.length" class="muted">Aucun ingrédient renseigné.</p>
        <ul v-else class="ingredient-list">
          <li v-for="ingredient in recipe.ingredients" :key="ingredient.position">
            <strong>{{ ingredientLabel(ingredient.quantity, ingredient.unit) }}</strong>
            {{ ingredient.name }}
            <span v-if="ingredient.preparation" class="muted"> — {{ ingredient.preparation }}</span>
            <span v-if="ingredient.is_optional" class="optional"> (facultatif)</span>
          </li>
        </ul>
      </section>

      <section class="detail-section" aria-labelledby="steps-heading">
        <h3 id="steps-heading">Étapes</h3>
        <p v-if="!recipe.steps?.length" class="muted">Aucune étape renseignée.</p>
        <ol v-else class="step-list">
          <li v-for="step in recipe.steps" :key="step.position">
            <p>{{ step.instruction }}</p>
            <small v-if="step.duration_minutes !== null">{{ step.duration_minutes }} min</small>
          </li>
        </ol>
      </section>
      <RecipeCommentsSection
        :recipe-id="recipe.id"
        :token-type="authStore.tokenType"
        :access-token="authStore.accessToken"
        :current-user-id="authStore.user?.id ?? null"
      />
    </article>
  </main>
</template>

<style scoped>
.detail-page { max-width: 48rem; margin: 0 auto; }
.back-link { color: #395330; font-weight: 700; }
.state-message { margin-top: 2rem; color: #50634d; }
.error-summary { padding: 1rem; border-radius: .8rem; color: #8f1e1e; background: #fff0ee; }
.error-summary button { display: block; margin-top: .75rem; padding: .5rem .7rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; font-weight: 700; cursor: pointer; }
.recipe-detail { margin-top: 1.5rem; padding: 2rem; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.recipe-image { display: block; width: 100%; max-height: 24rem; margin: 1.2rem 0; object-fit: cover; border-radius: 1rem; }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0; font-size: clamp(2rem, 5vw, 3.4rem); }
.author, .description, .source, .muted { color: #50634d; line-height: 1.6; }
.edit-link { display: inline-block; margin-top: .7rem; color: #395330; font-weight: 700; }
.cookbook-button { display: block; margin-top: .7rem; padding: .55rem .75rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.planning-button { display: block; margin-top: .7rem; padding: .55rem .75rem; border: 1px solid #6b7b57; border-radius: .5rem; background: #edf4e8; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.planning-success { color: #395330; font-weight: 700; }
.cookbook-picker { display: grid; gap: .55rem; max-width: 24rem; margin-top: .8rem; padding: .9rem; border: 1px solid rgba(86,112,79,.18); border-radius: .7rem; }
.cookbook-picker select { padding: .5rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.cookbook-picker-actions { display: flex; flex-wrap: wrap; gap: .6rem; }
.cookbook-picker-actions button { padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.delete-button { display: inline-block; margin: .7rem .5rem 0 0; padding: .55rem .75rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: #8f1e1e; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.delete-confirmation { margin-top: 1rem; padding: 1rem; border: 1px solid #e2b3ad; border-radius: .8rem; background: #fff8f6; }
.delete-confirmation h3 { margin-top: 0; color: #8f1e1e; }
.delete-confirmation p { color: #6d4140; line-height: 1.5; }
.delete-actions { display: flex; flex-wrap: wrap; gap: .6rem; }
.cancel-button { padding: .55rem .75rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.delete-error { color: #8f1e1e !important; font-weight: 700; }
button:disabled { cursor: wait; opacity: .55; }
.meta { display: flex; flex-wrap: wrap; gap: .5rem 1rem; margin: 1.2rem 0; color: #395330; font-weight: 700; }
.tags { display: flex; flex-wrap: wrap; gap: .4rem; }
.tag { padding: .3rem .55rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: .85rem; }
.source a { color: #395330; overflow-wrap: anywhere; }
.detail-section { margin-top: 2rem; padding-top: 1.2rem; border-top: 1px solid rgba(86,112,79,.18); }
h3 { margin: 0 0 .8rem; }
.ingredient-list, .step-list { display: grid; gap: .65rem; padding-left: 1.4rem; line-height: 1.5; }
.ingredient-list li::marker, .step-list li::marker { color: #6b7b57; font-weight: 700; }
.optional { color: #6b7b57; font-size: .9rem; }
.step-list p { margin: 0; }
.step-list small { color: #50634d; }
</style>
