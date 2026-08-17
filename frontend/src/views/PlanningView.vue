<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { deletePlannedMeal, fetchPlannedMeals, updatePlannedMeal, type PlannedMeal } from '@/utils/planning';
import { fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { fetchRecipes, type Recipe } from '@/utils/recipes';
import AddToPlanningModal from '@/components/AddToPlanningModal.vue';
import { useDialogFocus } from '@/utils/dialogFocus';

type CalendarMode = 'week' | 'month';
const authStore = useAuthStore();
const route = useRoute();
const cookbookId = computed(() => typeof route?.params?.id === 'string' ? route.params.id : undefined);
const mode = ref<CalendarMode>('week');
const currentDate = ref(startOfDay(new Date()));
const meals = ref<PlannedMeal[]>([]);
const selectedMeal = ref<PlannedMeal | null>(null);
const isEditing = ref(false);
const editForm = ref({ date: '', meal_type: 'dinner' as PlannedMeal['meal_type'], note: '', servings: 1 });
const editError = ref('');
const editFieldErrors = ref<Record<string, string>>({});
const isUpdating = ref(false);
const isDeleteConfirmationVisible = ref(false);
const deleteScope = ref<'occurrence' | 'series'>('occurrence');
const deleteError = ref('');
const isDeleting = ref(false);
const feedbackMessage = ref('');
const mealDialog = ref<HTMLElement | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const sourceSelectorOpen = ref(false);
const sourcesLoading = ref(false);
const sourcesError = ref('');
const cookbooks = ref<Cookbook[]>([]);
const includePersonal = ref(true);
const selectedCookbookIds = ref<string[]>([]);
const draftIncludePersonal = ref(true);
const draftCookbookIds = ref<string[]>([]);
const sourceSelectionActive = ref(false);
const recipePickerOpen = ref(false);
const recipePickerLoading = ref(false);
const recipePickerError = ref('');
const availableRecipes = ref<Recipe[]>([]);
const selectedRecipeId = ref('');
const addModalOpen = ref(false);
const selectedRecipe = computed(() => availableRecipes.value.find((recipe) => recipe.id === selectedRecipeId.value) ?? null);
const mealTypeLabels: Record<PlannedMeal['meal_type'], string> = {
  breakfast: 'Petit-déjeuner', lunch: 'Déjeuner', dinner: 'Dîner', snack: 'Collation',
};

function startOfDay(date: Date): Date { return new Date(date.getFullYear(), date.getMonth(), date.getDate()); }
function dateKey(date: Date): string {
  return [date.getFullYear(), date.getMonth() + 1, date.getDate()]
    .map((part, index) => index === 0 ? String(part) : String(part).padStart(2, '0')).join('-');
}
function fromDateKey(value: string): Date {
  const parts = value.split('-').map(Number);
  const year = parts[0] ?? 0;
  const month = parts[1] ?? 1;
  const day = parts[2] ?? 1;
  return new Date(year, month - 1, day);
}
function addDays(date: Date, days: number): Date {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return startOfDay(result);
}
function mondayOfWeek(date: Date): Date {
  const result = startOfDay(date);
  const day = result.getDay() || 7;
  result.setDate(result.getDate() - day + 1);
  return result;
}
function displayDate(value: string): string {
  return fromDateKey(value).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}
function ingredientLabel(quantity: number | null, unit: string | null): string {
  if (quantity === null && !unit) return '';
  return [quantity, unit].filter((value) => value !== null && value !== '').join(' ');
}
function displayMonth(date: Date): string { return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }); }
function displayWeek(start: Date): string {
  return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })} – ${addDays(start, 6).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })}`;
}

const period = computed(() => {
  if (mode.value === 'week') {
    const from = mondayOfWeek(currentDate.value);
    return { from: dateKey(from), to: dateKey(addDays(from, 6)) };
  }
  return {
    from: dateKey(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)),
    to: dateKey(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0)),
  };
});
const periodLabel = computed(() => mode.value === 'week' ? displayWeek(fromDateKey(period.value.from)) : displayMonth(currentDate.value));
const calendarDays = computed(() => {
  const first = mode.value === 'week'
    ? mondayOfWeek(currentDate.value)
    : mondayOfWeek(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1));
  return Array.from({ length: mode.value === 'week' ? 7 : 42 }, (_, index) => addDays(first, index));
});
const mealsByDate = computed(() => meals.value.reduce<Record<string, PlannedMeal[]>>((groups, meal) => {
  (groups[meal.date] ??= []).push(meal);
  return groups;
}, {}));
const isMealDialogOpen = computed(() => selectedMeal.value !== null);
function mealsFor(date: Date): PlannedMeal[] { return mealsByDate.value[dateKey(date)] ?? []; }
function isOutsideCurrentMonth(date: Date): boolean { return mode.value === 'month' && date.getMonth() !== currentDate.value.getMonth(); }
function movePeriod(direction: number): void {
  const next = new Date(currentDate.value);
  if (mode.value === 'week') next.setDate(next.getDate() + direction * 7);
  else next.setMonth(next.getMonth() + direction);
  currentDate.value = startOfDay(next);
}
function goToToday(): void { currentDate.value = startOfDay(new Date()); }
function closeDetail(): void {
  if (isUpdating.value || isDeleting.value) return;
  selectedMeal.value = null;
  isEditing.value = false;
  isDeleteConfirmationVisible.value = false;
  editError.value = '';
  deleteError.value = '';
}
function openMealDetail(meal: PlannedMeal): void {
  selectedMeal.value = meal;
  isEditing.value = false;
  isDeleteConfirmationVisible.value = false;
  editError.value = '';
  deleteError.value = '';
  feedbackMessage.value = '';
}
async function openRecipePicker(): Promise<void> {
  recipePickerOpen.value = true;
  recipePickerError.value = '';
  if (availableRecipes.value.length > 0) return;
  recipePickerLoading.value = true;
  const result = await fetchRecipes(authStore.tokenType, authStore.accessToken, 1, 'all');
  if (result.ok) availableRecipes.value = result.recipes;
  else recipePickerError.value = result.message;
  recipePickerLoading.value = false;
}
function openAddModal(): void {
  if (selectedRecipeId.value !== '') addModalOpen.value = true;
}
function closeAddModal(): void { addModalOpen.value = false; }
async function handleMealAdded(): Promise<void> {
  addModalOpen.value = false;
  recipePickerOpen.value = false;
  selectedRecipeId.value = '';
  await loadMeals();
}

function startEditing(): void {
  if (!selectedMeal.value) return;
  editForm.value = {
    date: selectedMeal.value.date,
    meal_type: selectedMeal.value.meal_type,
    note: selectedMeal.value.note ?? '',
    servings: selectedMeal.value.servings,
  };
  editError.value = '';
  editFieldErrors.value = {};
  isEditing.value = true;
}
function cancelEditing(): void {
  if (isUpdating.value) return;
  isEditing.value = false;
  editError.value = '';
  editFieldErrors.value = {};
}
function errorFor(field: string): string { return editFieldErrors.value[field] ?? ''; }
async function submitEdit(): Promise<void> {
  if (!selectedMeal.value) return;
  editError.value = '';
  editFieldErrors.value = {};
  isUpdating.value = true;
  const result = await updatePlannedMeal(selectedMeal.value.id, editForm.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    await loadMeals();
    feedbackMessage.value = 'Le repas planifié a été modifié.';
  } else {
    editError.value = result.message;
    editFieldErrors.value = result.fieldErrors;
  }
  isUpdating.value = false;
}
function startDeleteConfirmation(): void {
  deleteError.value = '';
  deleteScope.value = 'occurrence';
  isDeleteConfirmationVisible.value = true;
}
function cancelDelete(): void {
  if (isDeleting.value) return;
  isDeleteConfirmationVisible.value = false;
  deleteError.value = '';
}
async function confirmDelete(): Promise<void> {
  if (!selectedMeal.value) return;
  deleteError.value = '';
  isDeleting.value = true;
  const result = await deletePlannedMeal(selectedMeal.value.id, authStore.tokenType, authStore.accessToken, deleteScope.value);
  if (result.ok) {
    await loadMeals();
    feedbackMessage.value = 'Le repas planifié a été supprimé.';
  } else {
    deleteError.value = result.message;
  }
  isDeleting.value = false;
}
async function loadMeals(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  feedbackMessage.value = '';
  selectedMeal.value = null;
  const result = await fetchPlannedMeals(
    period.value.from,
    period.value.to,
    authStore.tokenType,
    authStore.accessToken,
    cookbookId.value,
    cookbookId.value || !sourceSelectionActive.value ? [] : selectedCookbookIds.value,
    cookbookId.value || !sourceSelectionActive.value ? undefined : includePersonal.value,
  );
  if (result.ok) meals.value = result.meals;
  else { meals.value = []; errorMessage.value = result.message; }
  isLoading.value = false;
}
async function openSourceSelector(): Promise<void> {
  sourceSelectorOpen.value = true;
  if (cookbooks.value.length > 0) {
    draftIncludePersonal.value = includePersonal.value;
    draftCookbookIds.value = [...selectedCookbookIds.value];
    return;
  }
  sourcesLoading.value = true;
  sourcesError.value = '';
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    cookbooks.value = result.data;
    draftIncludePersonal.value = sourceSelectionActive.value ? includePersonal.value : true;
    draftCookbookIds.value = sourceSelectionActive.value ? [...selectedCookbookIds.value] : result.data.map((cookbook) => cookbook.id);
  } else sourcesError.value = result.message;
  sourcesLoading.value = false;
}
function closeSourceSelector(): void { sourceSelectorOpen.value = false; }
async function applySourceSelection(): Promise<void> {
  includePersonal.value = draftIncludePersonal.value;
  selectedCookbookIds.value = [...draftCookbookIds.value];
  sourceSelectionActive.value = true;
  sourceSelectorOpen.value = false;
  await loadMeals();
}
async function resetSourceSelection(): Promise<void> {
  sourceSelectionActive.value = false;
  includePersonal.value = true;
  selectedCookbookIds.value = [];
  sourceSelectorOpen.value = false;
  await loadMeals();
}
watch([mode, currentDate], () => { void loadMeals(); });
onMounted(() => { void loadMeals(); });
useDialogFocus(mealDialog, isMealDialogOpen, closeDetail);
</script>

<template>
  <main class="planning-page">
    <header class="planning-header">
      <div><p class="kicker">Organisation</p><h2>Mon planning</h2><p class="period-label" aria-live="polite">{{ periodLabel }}</p></div>
      <button type="button" class="add-meal-button" @click="openRecipePicker">Ajouter un repas</button>
      <div class="view-switcher" role="group" aria-label="Vue du planning">
        <button type="button" :class="{ active: mode === 'week' }" @click="mode = 'week'">Semaine</button>
        <button type="button" :class="{ active: mode === 'month' }" @click="mode = 'month'">Mois</button>
      </div>
    </header>
    <section v-if="recipePickerOpen" class="recipe-picker" aria-labelledby="recipe-picker-title">
      <div>
        <h3 id="recipe-picker-title">Ajouter un repas</h3>
        <p>Choisissez une recette à planifier.</p>
      </div>
      <div v-if="recipePickerLoading" role="status">Chargement des recettes...</div>
      <p v-else-if="recipePickerError" class="source-error" role="alert">{{ recipePickerError }}</p>
      <div v-else class="recipe-picker-controls">
        <label for="planning-recipe">Recette</label>
        <select id="planning-recipe" v-model="selectedRecipeId">
          <option value="">Choisir une recette</option>
          <option v-for="recipe in availableRecipes" :key="recipe.id" :value="recipe.id">{{ recipe.title }}</option>
        </select>
        <button type="button" class="sources-button" :disabled="!selectedRecipeId" @click="openAddModal">Continuer</button>
        <button type="button" class="secondary-source-button" @click="recipePickerOpen = false">Annuler</button>
      </div>
    </section>
    <section v-if="!cookbookId" class="planning-sources" aria-labelledby="planning-sources-title">
      <div class="sources-heading">
        <div><h3 id="planning-sources-title">Sources affichées</h3><p>Choisissez votre planning personnel, un ou plusieurs cookbooks, ou une combinaison.</p></div>
        <button type="button" class="sources-button" @click="openSourceSelector">Modifier les sources</button>
      </div>
      <p v-if="sourceSelectionActive" class="source-summary" aria-live="polite">{{ includePersonal ? 'Planning personnel' : 'Aucun planning personnel' }}<span v-if="selectedCookbookIds.length"> · {{ selectedCookbookIds.length }} cookbook<span v-if="selectedCookbookIds.length > 1">s</span></span></p>
      <p v-else class="source-summary">Planning personnel et tous les cookbooks accessibles</p>
      <div v-if="sourceSelectorOpen" class="sources-panel">
        <p v-if="sourcesLoading" role="status">Chargement des cookbooks...</p>
        <p v-else-if="sourcesError" class="source-error" role="alert">{{ sourcesError }}</p>
        <fieldset v-else>
          <legend>Inclure dans le planning</legend>
          <label><input v-model="draftIncludePersonal" type="checkbox" /> Mon planning personnel</label>
          <label v-for="cookbook in cookbooks" :key="cookbook.id"><input v-model="draftCookbookIds" type="checkbox" :value="cookbook.id" /> {{ cookbook.name }}</label>
        </fieldset>
        <div class="source-actions"><button type="button" class="secondary-source-button" @click="resetSourceSelection">Réinitialiser</button><button type="button" class="secondary-source-button" @click="closeSourceSelector">Annuler</button><button type="button" class="sources-button" :disabled="sourcesLoading || !!sourcesError" @click="applySourceSelection">Appliquer</button></div>
      </div>
    </section>
    <nav class="calendar-navigation" aria-label="Navigation du planning">
      <button type="button" class="period-button" aria-label="Période précédente" @click="movePeriod(-1)">‹</button>
      <button type="button" class="today-button" @click="goToToday">Aujourd’hui</button>
      <button type="button" class="period-button" aria-label="Période suivante" @click="movePeriod(1)">›</button>
    </nav>
    <p v-if="isLoading" class="state-message" role="status">Chargement du planning...</p>
    <section v-else-if="errorMessage" class="state-message error-state" role="alert"><p>{{ errorMessage }}</p><button type="button" @click="loadMeals">Réessayer</button></section>
    <section v-if="!isLoading && !errorMessage" class="calendar" :class="`calendar-${mode}`" :aria-label="`Planning ${mode}`">
      <div class="weekday-row" aria-hidden="true"><span v-for="day in ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']" :key="day">{{ day }}</span></div>
      <div class="calendar-grid">
        <article v-for="day in calendarDays" :key="dateKey(day)" class="calendar-day" :class="{ outside: isOutsideCurrentMonth(day), today: dateKey(day) === dateKey(new Date()) }" :aria-current="dateKey(day) === dateKey(new Date()) ? 'date' : undefined">
          <h3>{{ day.getDate() }}</h3>
          <button v-for="meal in mealsFor(day)" :key="meal.id" type="button" class="meal-card" @click="openMealDetail(meal)"><strong>{{ meal.recipe.title }}</strong><span>{{ mealTypeLabels[meal.meal_type] }} · {{ meal.servings }} portion<span v-if="meal.servings > 1">s</span></span></button>
        </article>
      </div>
      <p v-if="meals.length === 0" class="calendar-empty">Aucun repas planifié sur cette période.</p>
      <div class="mobile-agenda">
        <article v-for="day in calendarDays.filter((candidate) => mealsFor(candidate).length > 0)" :key="`mobile-${dateKey(day)}`" class="agenda-day" :class="{ today: dateKey(day) === dateKey(new Date()) }">
          <h3><span>{{ day.toLocaleDateString('fr-FR', { weekday: 'long' }) }}</span><time :datetime="dateKey(day)">{{ day.getDate() }} {{ day.toLocaleDateString('fr-FR', { month: 'long' }) }}</time></h3>
          <button v-for="meal in mealsFor(day)" :key="meal.id" type="button" class="meal-card" @click="openMealDetail(meal)"><strong>{{ meal.recipe.title }}</strong><span>{{ mealTypeLabels[meal.meal_type] }} · {{ meal.servings }} portion<span v-if="meal.servings > 1">s</span></span></button>
        </article>
        <p v-if="meals.length === 0" class="calendar-empty">Aucun repas planifié sur cette période.</p>
      </div>
    </section>
    <div v-if="selectedMeal" class="detail-backdrop" role="presentation" @click.self="closeDetail">
      <section ref="mealDialog" class="meal-detail" role="dialog" aria-modal="true" aria-labelledby="meal-detail-title" tabindex="-1">
        <form v-if="isEditing" class="edit-meal-form" @submit.prevent="submitEdit">
          <h3 id="meal-detail-title">Modifier le repas</h3>
          <section class="edit-meal-summary" aria-label="Informations du repas">
            <div><span class="summary-label">Recette</span><RouterLink :to="{ name: 'recipe-detail', params: { id: selectedMeal.recipe.id } }">{{ selectedMeal.recipe.title }}</RouterLink></div>
            <div><span class="summary-label">Portions</span><span>{{ selectedMeal.servings }} portion<span v-if="selectedMeal.servings > 1">s</span></span></div>
            <div v-if="selectedMeal.cookbook_id"><span class="summary-label">Cookbook</span><span>Repas du cookbook</span></div>
            <div><span class="summary-label">Répétition</span><span v-if="selectedMeal.recurrence">Chaque semaine jusqu’au {{ selectedMeal.recurrence.until }}</span><span v-else>Sans répétition</span></div>
          </section>
          <label for="edit-meal-date">Date</label>
          <input id="edit-meal-date" v-model="editForm.date" type="date" />
          <p v-if="errorFor('date')" class="form-error" role="alert">{{ errorFor('date') }}</p>
          <label for="edit-meal-type">Type de repas</label>
          <select id="edit-meal-type" v-model="editForm.meal_type">
            <option value="breakfast">Petit-déjeuner</option><option value="lunch">Déjeuner</option><option value="dinner">Dîner</option><option value="snack">Collation</option>
          </select>
          <p v-if="errorFor('meal_type')" class="form-error" role="alert">{{ errorFor('meal_type') }}</p>
          <label for="edit-meal-note">Note</label>
          <textarea id="edit-meal-note" v-model="editForm.note" rows="3" maxlength="5000" />
          <label for="edit-meal-servings">Portions</label>
          <input id="edit-meal-servings" v-model.number="editForm.servings" type="number" min="1" max="1000" step="1" />
          <p v-if="errorFor('servings')" class="form-error" role="alert">{{ errorFor('servings') }}</p>
          <p v-if="editError" class="form-error" role="alert">{{ editError }}</p>
          <div class="detail-actions">
            <button type="submit" class="edit-detail-button" :disabled="isUpdating">{{ isUpdating ? 'Enregistrement...' : 'Enregistrer' }}</button>
            <button type="button" class="cancel-detail-button" :disabled="isUpdating" @click="cancelEditing">Annuler</button>
          </div>
          <div v-if="!isDeleteConfirmationVisible" class="edit-danger-zone">
            <p>La suppression est définitive.</p>
            <button type="button" class="delete-detail-button" @click="startDeleteConfirmation">Supprimer ce repas</button>
          </div>
          <section v-else class="delete-confirmation" aria-labelledby="delete-meal-heading-edit">
            <h4 id="delete-meal-heading-edit">Supprimer ce repas planifié ?</h4>
            <p>Cette action est définitive.</p>
            <fieldset v-if="selectedMeal.recurrence" class="delete-scope">
              <legend>Portée de la suppression</legend>
              <label><input v-model="deleteScope" type="radio" value="occurrence" /> Cette occurrence uniquement</label>
              <label><input v-model="deleteScope" type="radio" value="series" /> Toute la série</label>
            </fieldset>
            <p v-if="deleteError" class="form-error" role="alert">{{ deleteError }}</p>
            <div class="detail-actions">
              <button type="button" class="delete-detail-button" :disabled="isDeleting" @click="confirmDelete">{{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}</button>
              <button type="button" class="cancel-detail-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button>
            </div>
          </section>
        </form>
        <template v-else>
        <button type="button" class="close-detail" aria-label="Fermer le détail" @click="closeDetail">×</button>
        <p class="kicker">{{ displayDate(selectedMeal.date) }}</p><h3 id="meal-detail-title">{{ selectedMeal.recipe.title }}</h3>
        <p>{{ mealTypeLabels[selectedMeal.meal_type] }} · {{ selectedMeal.servings }} portion<span v-if="selectedMeal.servings > 1">s</span></p>
        <RouterLink class="recipe-link" :to="{ name: 'recipe-detail', params: { id: selectedMeal.recipe.id } }">Voir la recette associée</RouterLink>
        <p v-if="selectedMeal.note" class="detail-note">{{ selectedMeal.note }}</p>
        <p v-if="selectedMeal.cookbook_id" class="detail-space">Repas du cookbook</p>
        <section v-if="selectedMeal.recipe.ingredients?.length" class="meal-ingredients" aria-labelledby="meal-ingredients-title">
          <h4 id="meal-ingredients-title">Ingrédients</h4>
          <ul>
            <li v-for="ingredient in selectedMeal.recipe.ingredients" :key="ingredient.position">
              <strong>{{ ingredientLabel(ingredient.quantity, ingredient.unit) }}</strong>
              {{ ingredient.name }}<span v-if="ingredient.preparation" class="ingredient-preparation"> — {{ ingredient.preparation }}</span><span v-if="ingredient.is_optional" class="ingredient-optional"> (facultatif)</span>
            </li>
          </ul>
        </section>
        <div v-if="!isDeleteConfirmationVisible" class="detail-actions">
          <button type="button" class="edit-detail-button" @click="startEditing">Modifier</button>
        </div>
        </template>
      </section>
    </div>
    <p v-if="feedbackMessage" class="feedback-message" role="status">{{ feedbackMessage }}</p>
    <AddToPlanningModal v-if="addModalOpen && selectedRecipe" :recipe="selectedRecipe" @close="closeAddModal" @added="handleMealAdded" />
  </main>
</template>

<style scoped>
.planning-page { width: 100%; max-width: 76rem; margin: 0 auto; padding: 1.5rem; box-sizing: border-box; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.planning-header { display: flex; justify-content: space-between; gap: 1rem; align-items: end; }
.add-meal-button { padding: .7rem 1rem; border: 1px solid #395330; border-radius: .6rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.recipe-picker { display: grid; gap: .8rem; margin: 1rem 0; padding: 1rem; border: 1px solid #b9c5af; border-radius: .8rem; background: #f3f7ef; }
.recipe-picker h3 { margin: 0; }
.recipe-picker p { margin: .25rem 0 0; color: #50634d; }
.recipe-picker-controls { display: flex; flex-wrap: wrap; align-items: end; gap: .6rem; }
.recipe-picker-controls label { display: grid; gap: .3rem; min-width: min(100%, 20rem); font-weight: 700; }
.recipe-picker-controls select { padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
.planning-sources { margin: 1.25rem 0; padding: 1rem; border: 1px solid #b9c5af; border-radius: .8rem; background: rgba(247, 251, 243, .7); }
.sources-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.sources-heading h3 { margin: 0; font-size: 1.05rem; }
.sources-heading p { margin: .3rem 0 0; color: #50634d; }
.sources-button, .secondary-source-button { padding: .55rem .75rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.secondary-source-button { background: transparent; color: #395330; }
.source-summary { margin: .75rem 0 0; color: #395330; font-size: .9rem; font-weight: 700; }
.sources-panel { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #b9c5af; }
.sources-panel fieldset { display: grid; gap: .55rem; margin: 0; padding: 0; border: 0; }
.sources-panel legend { margin-bottom: .4rem; font-weight: 700; }
.source-actions { display: flex; flex-wrap: wrap; justify-content: end; gap: .5rem; margin-top: 1rem; }
.source-error { color: #8f1e1e; }
.kicker { margin: 0 0 .3rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h2, h3 { margin: 0; color: #243127; }
.period-label { margin: .4rem 0 0; color: #50634d; text-transform: capitalize; }
.view-switcher, .calendar-navigation { display: flex; gap: .4rem; }
.view-switcher button, .calendar-navigation button, .error-state button { padding: .55rem .75rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.view-switcher button.active { background: #395330; border-color: #395330; color: #fffdf8; }
.calendar-navigation { justify-content: center; align-items: center; margin: 1.5rem 0 1rem; }
.period-button { width: 2.3rem; font-size: 1.4rem !important; line-height: 1; }
.today-button { min-width: 7rem; }
.state-message { margin: 2rem 0; padding: 2rem 1rem; border-radius: .8rem; color: #50634d; text-align: center; }
.empty-state { background: #f3f7ef; }
.error-state { background: #fff0ee; color: #8f1e1e; }
.error-state p { margin: 0 0 .8rem; }
.calendar { overflow: hidden; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; }
.weekday-row, .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.weekday-row { background: #edf4e8; color: #395330; font-size: .8rem; font-weight: 700; text-align: center; text-transform: uppercase; }
.weekday-row span { padding: .65rem .25rem; }
.calendar-day { min-height: 8rem; padding: .55rem; border-top: 1px solid rgba(86,112,79,.15); border-right: 1px solid rgba(86,112,79,.15); background: #fffdf8; }
.calendar-day:nth-child(7n) { border-right: 0; }
.calendar-day.outside { background: #f7f5ef; color: #9aa493; }
.calendar-day.today h3 { display: inline-grid; width: 1.7rem; height: 1.7rem; place-items: center; border-radius: 50%; background: #395330; color: #fffdf8; }
.calendar-day h3 { margin-bottom: .45rem; font-size: .9rem; }
.meal-card { display: grid; width: 100%; gap: .15rem; margin-bottom: .35rem; padding: .45rem; border: 1px solid #b9c5af; border-radius: .45rem; background: #f3f7ef; color: #243127; font: inherit; text-align: left; cursor: pointer; }
.meal-card strong { overflow: hidden; font-size: .8rem; text-overflow: ellipsis; white-space: nowrap; }
.meal-card span { color: #50634d; font-size: .72rem; }
.calendar-empty { margin: 0; padding: 2rem 1rem; color: #50634d; text-align: center; }
.mobile-agenda { display: none; }
.recipe-link { display: inline-block; color: #395330; font-weight: 700; }
.detail-backdrop { position: fixed; inset: 0; z-index: 5; display: grid; place-items: center; padding: 1rem; background: rgba(36,49,39,.48); }
.meal-detail { position: relative; width: min(100%, 25rem); padding: 1.5rem; border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(36,49,39,.25); }
.meal-detail h3 { margin: .3rem 0 .7rem; font-size: 1.5rem; }
.edit-meal-summary { display: grid; gap: .55rem; margin: .2rem 0 .5rem; padding: .8rem; border: 1px solid rgba(86,112,79,.2); border-radius: .7rem; background: #f3f7ef; }
.edit-meal-summary > div { display: flex; justify-content: space-between; gap: .8rem; }
.summary-label { color: #50634d; font-size: .85rem; font-weight: 700; }
.edit-meal-summary a { color: #395330; font-weight: 700; text-align: right; }
.meal-detail p { color: #50634d; line-height: 1.5; }
.close-detail { position: absolute; top: .75rem; right: .75rem; border: 0; background: transparent; color: #395330; font-size: 1.6rem; cursor: pointer; }
.detail-note { padding: .7rem; border-radius: .5rem; background: #f3f7ef; }
.detail-space { font-size: .85rem; }
.meal-ingredients { margin: 1rem 0; padding: .8rem; border-radius: .6rem; background: #f3f7ef; }
.meal-ingredients h4 { margin: 0 0 .5rem; color: #243127; }
.meal-ingredients ul { display: grid; gap: .35rem; margin: 0; padding-left: 1.2rem; color: #395330; }
.ingredient-preparation, .ingredient-optional { color: #6d7768; }
.detail-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1rem; }
.edit-danger-zone { display: flex; align-items: center; justify-content: space-between; gap: .8rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2b3ad; }
.edit-danger-zone p { margin: 0; color: #6d4140; font-size: .85rem; }
.edit-detail-button, .delete-detail-button, .cancel-detail-button { padding: .55rem .75rem; border: 1px solid #395330; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.edit-detail-button { background: #395330; color: #fffdf8; }
.delete-detail-button { border-color: #8f1e1e; background: #8f1e1e; color: #fffdf8; }
.cancel-detail-button { background: transparent; color: #395330; }
.edit-meal-form { display: grid; gap: .55rem; }
.edit-meal-form h3 { margin-bottom: .4rem; }
.edit-meal-form input, .edit-meal-form select, .edit-meal-form textarea { box-sizing: border-box; width: 100%; padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
.form-error { margin: 0; padding: .55rem; border-radius: .5rem; background: #fff0ee; color: #8f1e1e; font-size: .9rem; }
.delete-confirmation { margin-top: 1rem; padding: .8rem; border: 1px solid #e2b3ad; border-radius: .7rem; background: #fff8f6; }
.delete-scope { display: grid; gap: .45rem; margin: .7rem 0; padding: .6rem; border: 1px solid #e2b3ad; border-radius: .5rem; color: #6d4140; }
.delete-confirmation h4 { margin: 0; color: #8f1e1e; }
.delete-confirmation p { color: #6d4140; }
.feedback-message { margin: 1rem 0 0; color: #395330; font-weight: 700; text-align: center; }
button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 640px) {
  .planning-page { padding: 1rem .6rem; border-radius: 1rem; }
  .planning-header { display: grid; align-items: start; }
  .add-meal-button { width: 100%; }
  .sources-heading { align-items: flex-start; flex-direction: column; }
  .sources-button { width: 100%; }
  .view-switcher { width: 100%; }
  .view-switcher button { flex: 1; }
  .calendar-grid, .weekday-row { display: none; }
  .calendar > .calendar-empty { display: none; }
  .mobile-agenda { display: grid; gap: .7rem; padding: .7rem; background: #f7f5ef; }
  .agenda-day { padding: .7rem; border: 1px solid rgba(86,112,79,.2); border-radius: .7rem; background: #fffdf8; }
  .agenda-day.today { border-color: #395330; box-shadow: inset .25rem 0 #395330; }
  .agenda-day h3 { display: flex; justify-content: space-between; gap: .5rem; margin: 0 0 .6rem; color: #243127; font-size: .95rem; text-transform: capitalize; }
  .agenda-day time { color: #50634d; font-size: .85rem; font-weight: 400; }
  .meal-card { padding: .65rem; }
  .meal-card strong { font-size: .9rem; white-space: normal; }
  .meal-card span { font-size: .8rem; }
  .recipe-picker-controls { align-items: stretch; flex-direction: column; }
  .recipe-picker-controls label, .recipe-picker-controls select, .recipe-picker-controls button { width: 100%; box-sizing: border-box; }
}
</style>
