<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { deletePlannedMeal, fetchPlannedMeals, updatePlannedMeal, type PlannedMeal } from '@/utils/planning';
import { useDialogFocus } from '@/utils/dialogFocus';

type CalendarMode = 'week' | 'month';
const authStore = useAuthStore();
const mode = ref<CalendarMode>('week');
const currentDate = ref(startOfDay(new Date()));
const meals = ref<PlannedMeal[]>([]);
const selectedMeal = ref<PlannedMeal | null>(null);
const isEditing = ref(false);
const editForm = ref({ date: '', meal_type: 'dinner' as PlannedMeal['meal_type'], note: '' });
const editError = ref('');
const editFieldErrors = ref<Record<string, string>>({});
const isUpdating = ref(false);
const isDeleteConfirmationVisible = ref(false);
const deleteError = ref('');
const isDeleting = ref(false);
const feedbackMessage = ref('');
const mealDialog = ref<HTMLElement | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
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
function startEditing(): void {
  if (!selectedMeal.value) return;
  editForm.value = {
    date: selectedMeal.value.date,
    meal_type: selectedMeal.value.meal_type,
    note: selectedMeal.value.note ?? '',
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
  const result = await deletePlannedMeal(selectedMeal.value.id, authStore.tokenType, authStore.accessToken);
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
  const result = await fetchPlannedMeals(period.value.from, period.value.to, authStore.tokenType, authStore.accessToken);
  if (result.ok) meals.value = result.meals;
  else { meals.value = []; errorMessage.value = result.message; }
  isLoading.value = false;
}
watch([mode, currentDate], () => { void loadMeals(); });
onMounted(() => { void loadMeals(); });
useDialogFocus(mealDialog, isMealDialogOpen, closeDetail);
</script>

<template>
  <main class="planning-page">
    <header class="planning-header">
      <div><p class="kicker">Organisation</p><h2>Mon planning</h2><p class="period-label" aria-live="polite">{{ periodLabel }}</p></div>
      <div class="view-switcher" role="group" aria-label="Vue du planning">
        <button type="button" :class="{ active: mode === 'week' }" @click="mode = 'week'">Semaine</button>
        <button type="button" :class="{ active: mode === 'month' }" @click="mode = 'month'">Mois</button>
      </div>
    </header>
    <nav class="calendar-navigation" aria-label="Navigation du planning">
      <button type="button" class="period-button" aria-label="Période précédente" @click="movePeriod(-1)">‹</button>
      <button type="button" class="today-button" @click="goToToday">Aujourd’hui</button>
      <button type="button" class="period-button" aria-label="Période suivante" @click="movePeriod(1)">›</button>
    </nav>
    <p v-if="isLoading" class="state-message" role="status">Chargement du planning...</p>
    <section v-else-if="errorMessage" class="state-message error-state" role="alert"><p>{{ errorMessage }}</p><button type="button" @click="loadMeals">Réessayer</button></section>
    <p v-else-if="meals.length === 0" class="state-message empty-state">Aucun repas planifié sur cette période.</p>
    <section v-else class="calendar" :class="`calendar-${mode}`" :aria-label="`Planning ${mode}`">
      <div class="weekday-row" aria-hidden="true"><span v-for="day in ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']" :key="day">{{ day }}</span></div>
      <div class="calendar-grid">
        <article v-for="day in calendarDays" :key="dateKey(day)" class="calendar-day" :class="{ outside: isOutsideCurrentMonth(day), today: dateKey(day) === dateKey(new Date()) }">
          <h3>{{ day.getDate() }}</h3>
          <button v-for="meal in mealsFor(day)" :key="meal.id" type="button" class="meal-card" @click="openMealDetail(meal)"><strong>{{ meal.recipe.title }}</strong><span>{{ mealTypeLabels[meal.meal_type] }}</span></button>
        </article>
      </div>
    </section>
    <div v-if="selectedMeal" class="detail-backdrop" role="presentation" @click.self="closeDetail">
      <section ref="mealDialog" class="meal-detail" role="dialog" aria-modal="true" aria-labelledby="meal-detail-title" tabindex="-1">
        <form v-if="isEditing" class="edit-meal-form" @submit.prevent="submitEdit">
          <h3 id="meal-detail-title">Modifier le repas</h3>
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
          <p v-if="editError" class="form-error" role="alert">{{ editError }}</p>
          <div class="detail-actions">
            <button type="submit" class="edit-detail-button" :disabled="isUpdating">{{ isUpdating ? 'Enregistrement...' : 'Enregistrer' }}</button>
            <button type="button" class="cancel-detail-button" :disabled="isUpdating" @click="cancelEditing">Annuler</button>
          </div>
        </form>
        <template v-else>
        <button type="button" class="close-detail" aria-label="Fermer le détail" @click="closeDetail">×</button>
        <p class="kicker">{{ displayDate(selectedMeal.date) }}</p><h3 id="meal-detail-title">{{ selectedMeal.recipe.title }}</h3>
        <p>{{ mealTypeLabels[selectedMeal.meal_type] }} · {{ selectedMeal.initial_servings }} portion<span v-if="selectedMeal.initial_servings > 1">s</span></p>
        <p v-if="selectedMeal.note" class="detail-note">{{ selectedMeal.note }}</p>
        <p v-if="selectedMeal.cookbook_id" class="detail-space">Repas du cookbook</p>
        <div v-if="!isDeleteConfirmationVisible" class="detail-actions">
          <button type="button" class="edit-detail-button" @click="startEditing">Modifier</button>
          <button type="button" class="delete-detail-button" @click="startDeleteConfirmation">Supprimer</button>
        </div>
        <section v-else class="delete-confirmation" aria-labelledby="delete-meal-heading">
          <h4 id="delete-meal-heading">Supprimer ce repas planifié ?</h4>
          <p>Cette action est définitive.</p>
          <p v-if="deleteError" class="form-error" role="alert">{{ deleteError }}</p>
          <div class="detail-actions">
            <button type="button" class="delete-detail-button" :disabled="isDeleting" @click="confirmDelete">{{ isDeleting ? 'Suppression...' : 'Confirmer la suppression' }}</button>
            <button type="button" class="cancel-detail-button" :disabled="isDeleting" @click="cancelDelete">Annuler</button>
          </div>
        </section>
        </template>
      </section>
    </div>
    <p v-if="feedbackMessage" class="feedback-message" role="status">{{ feedbackMessage }}</p>
  </main>
</template>

<style scoped>
.planning-page { max-width: 64rem; margin: 0 auto; padding: 1.5rem; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.planning-header { display: flex; justify-content: space-between; gap: 1rem; align-items: end; }
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
.detail-backdrop { position: fixed; inset: 0; z-index: 5; display: grid; place-items: center; padding: 1rem; background: rgba(36,49,39,.48); }
.meal-detail { position: relative; width: min(100%, 25rem); padding: 1.5rem; border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(36,49,39,.25); }
.meal-detail h3 { margin: .3rem 0 .7rem; font-size: 1.5rem; }
.meal-detail p { color: #50634d; line-height: 1.5; }
.close-detail { position: absolute; top: .75rem; right: .75rem; border: 0; background: transparent; color: #395330; font-size: 1.6rem; cursor: pointer; }
.detail-note { padding: .7rem; border-radius: .5rem; background: #f3f7ef; }
.detail-space { font-size: .85rem; }
.detail-actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1rem; }
.edit-detail-button, .delete-detail-button, .cancel-detail-button { padding: .55rem .75rem; border: 1px solid #395330; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.edit-detail-button { background: #395330; color: #fffdf8; }
.delete-detail-button { border-color: #8f1e1e; background: #8f1e1e; color: #fffdf8; }
.cancel-detail-button { background: transparent; color: #395330; }
.edit-meal-form { display: grid; gap: .55rem; }
.edit-meal-form h3 { margin-bottom: .4rem; }
.edit-meal-form input, .edit-meal-form select, .edit-meal-form textarea { box-sizing: border-box; width: 100%; padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
.form-error { margin: 0; padding: .55rem; border-radius: .5rem; background: #fff0ee; color: #8f1e1e; font-size: .9rem; }
.delete-confirmation { margin-top: 1rem; padding: .8rem; border: 1px solid #e2b3ad; border-radius: .7rem; background: #fff8f6; }
.delete-confirmation h4 { margin: 0; color: #8f1e1e; }
.delete-confirmation p { color: #6d4140; }
.feedback-message { margin: 1rem 0 0; color: #395330; font-weight: 700; text-align: center; }
button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 640px) {
  .planning-page { padding: 1rem .6rem; border-radius: 1rem; }
  .planning-header { display: grid; align-items: start; }
  .view-switcher { width: 100%; }
  .view-switcher button { flex: 1; }
  .calendar-day { min-height: 6.5rem; padding: .35rem; }
  .meal-card { padding: .3rem; }
  .meal-card strong { font-size: .7rem; }
  .meal-card span { display: none; }
}
</style>
