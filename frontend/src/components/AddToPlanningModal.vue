<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { fetchCookbooks, type Cookbook } from '@/utils/cookbooks';
import { createPlannedMeal, type PlannedMealInput, type Recipe } from '@/utils/recipes';
import { useDialogFocus } from '@/utils/dialogFocus';

const props = defineProps<{ recipe: Recipe }>();
const emit = defineEmits<{ close: []; added: [] }>();

const authStore = useAuthStore();
const cookbooks = ref<Cookbook[]>([]);
const date = ref(new Date().toISOString().slice(0, 10));
const mealType = ref<PlannedMealInput['meal_type']>('dinner');
const recurrenceFrequency = ref<'none' | 'weekly'>('none');
const recurrenceUntil = ref('');
const isRecurrenceConfirmationVisible = ref(false);
const destination = ref<'personal' | 'cookbook'>('personal');
const selectedCookbookId = ref('');
const isLoadingCookbooks = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref<Record<string, string>>({});
const dialog = ref<HTMLElement | null>(null);
const isOpen = ref(true);

const cookbookRequired = computed(() => destination.value === 'cookbook' && selectedCookbookId.value === '');
const hasRecurrence = computed(() => recurrenceFrequency.value !== 'none');

async function loadCookbooks(): Promise<void> {
  isLoadingCookbooks.value = true;
  errorMessage.value = '';
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) cookbooks.value = result.data;
  else errorMessage.value = result.message;
  isLoadingCookbooks.value = false;
}

function errorFor(field: string): string {
  return fieldErrors.value[field] ?? '';
}

function validateRecurrence(): boolean {
  if (!hasRecurrence.value) return true;
  if (!recurrenceUntil.value) {
    fieldErrors.value.recurrence_until = 'Choisissez la fin de la série.';
    return false;
  }
  if (recurrenceUntil.value < date.value) {
    fieldErrors.value.recurrence_until = 'La fin doit être postérieure ou égale à la date de début.';
    return false;
  }
  return true;
}

function input(): PlannedMealInput {
  return {
    recipe_id: props.recipe.id,
    date: date.value,
    meal_type: mealType.value,
    cookbook_id: destination.value === 'cookbook' ? selectedCookbookId.value : null,
    ...(hasRecurrence.value ? { recurrence: { frequency: 'weekly' as const, until: recurrenceUntil.value } } : {}),
  };
}

async function submit(): Promise<void> {
  errorMessage.value = '';
  fieldErrors.value = {};

  if (cookbookRequired.value) {
    fieldErrors.value.cookbook_id = 'Choisissez un cookbook.';
    return;
  }

  if (!validateRecurrence()) return;
  if (hasRecurrence.value && !isRecurrenceConfirmationVisible.value) {
    isRecurrenceConfirmationVisible.value = true;
    return;
  }

  isSubmitting.value = true;
  const result = await createPlannedMeal(input(), authStore.tokenType, authStore.accessToken);

  if (result.ok) emit('added');
  else {
    errorMessage.value = result.message;
    fieldErrors.value = result.fieldErrors;
  }
  isSubmitting.value = false;
}

function cancelRecurrenceConfirmation(): void { isRecurrenceConfirmationVisible.value = false; }

onMounted(() => { void loadCookbooks(); });
useDialogFocus(dialog, isOpen, () => {
  if (!isSubmitting.value) emit('close');
});
</script>

<template>
  <div class="modal-backdrop" role="presentation" @click.self="emit('close')">
    <section ref="dialog" class="planning-modal" role="dialog" aria-modal="true" aria-labelledby="planning-modal-title" tabindex="-1">
      <div class="modal-header">
        <div>
          <p class="kicker">Planning</p>
          <h3 id="planning-modal-title">Ajouter « {{ recipe.title }} »</h3>
        </div>
        <button type="button" class="close-button" aria-label="Fermer" :disabled="isSubmitting" @click="emit('close')">×</button>
      </div>

      <form novalidate @submit.prevent="submit">
        <label for="planning-date">Date</label>
        <input id="planning-date" v-model="date" type="date" :aria-invalid="errorFor('date') ? 'true' : 'false'" />
        <p v-if="errorFor('date')" class="field-error" role="alert">{{ errorFor('date') }}</p>

        <label for="planning-meal-type">Type de repas</label>
        <select id="planning-meal-type" v-model="mealType">
          <option value="breakfast">Petit-déjeuner</option>
          <option value="lunch">Déjeuner</option>
          <option value="dinner">Dîner</option>
          <option value="snack">Collation</option>
        </select>
        <p v-if="errorFor('meal_type')" class="field-error" role="alert">{{ errorFor('meal_type') }}</p>

        <fieldset>
          <legend>Espace de planification</legend>
          <label class="radio-label"><input v-model="destination" type="radio" value="personal" /> Espace personnel</label>
          <label class="radio-label"><input v-model="destination" type="radio" value="cookbook" /> Un cookbook</label>
        </fieldset>

        <template v-if="destination === 'cookbook'">
          <label for="planning-cookbook">Cookbook</label>
          <select id="planning-cookbook" v-model="selectedCookbookId" :disabled="isLoadingCookbooks || isSubmitting">
            <option value="">Choisir un cookbook</option>
            <option v-for="cookbook in cookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
          </select>
          <p v-if="isLoadingCookbooks" class="muted" role="status">Chargement des cookbooks...</p>
          <p v-if="errorFor('cookbook_id')" class="field-error" role="alert">{{ errorFor('cookbook_id') }}</p>
        </template>

        <fieldset aria-labelledby="planning-recurrence-title">
          <legend id="planning-recurrence-title">Répétition</legend>
          <label for="planning-recurrence-frequency">Fréquence</label>
          <select id="planning-recurrence-frequency" v-model="recurrenceFrequency" :disabled="isSubmitting">
            <option value="none">Aucune</option>
            <option value="weekly">Chaque semaine</option>
          </select>
          <template v-if="hasRecurrence">
            <label for="planning-recurrence-until">Fin de la série</label>
            <input id="planning-recurrence-until" v-model="recurrenceUntil" type="date" :min="date" :disabled="isSubmitting" :aria-invalid="errorFor('recurrence_until') ? 'true' : 'false'" />
            <p v-if="errorFor('recurrence_until')" class="field-error" role="alert">{{ errorFor('recurrence_until') }}</p>
          </template>
        </fieldset>

        <section v-if="isRecurrenceConfirmationVisible" class="recurrence-confirmation" aria-labelledby="planning-recurrence-confirm-title">
          <h4 id="planning-recurrence-confirm-title">Confirmer la série ?</h4>
          <p>Ce repas sera ajouté chaque semaine jusqu’au {{ recurrenceUntil }}.</p>
          <div class="modal-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="cancelRecurrenceConfirmation">Modifier</button>
            <button type="submit" class="confirm-button" :disabled="isSubmitting">Confirmer la série</button>
          </div>
        </section>

        <p v-if="errorMessage" class="form-error" role="alert">{{ errorMessage }}</p>
        <div v-if="!isRecurrenceConfirmationVisible" class="modal-actions">
          <button type="button" class="cancel-button" :disabled="isSubmitting" @click="emit('close')">Annuler</button>
          <button type="submit" class="confirm-button" :disabled="isSubmitting || isLoadingCookbooks">
            {{ isSubmitting ? 'Ajout...' : 'Confirmer l’ajout' }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; z-index: 10; display: grid; place-items: center; padding: 1rem; background: rgba(36,49,39,.48); }
.planning-modal { width: min(100%, 30rem); max-height: calc(100vh - 2rem); overflow: auto; padding: 1.5rem; border: 1px solid rgba(86,112,79,.2); border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(36,49,39,.25); }
.modal-header { display: flex; justify-content: space-between; gap: 1rem; align-items: start; margin-bottom: 1.2rem; }
.kicker { margin: 0 0 .3rem; color: #6b7b57; font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
h3 { margin: 0; color: #243127; }
.close-button { border: 0; background: transparent; color: #395330; font-size: 1.7rem; line-height: 1; cursor: pointer; }
form { display: grid; gap: .55rem; }
input[type='date'], select { width: 100%; box-sizing: border-box; padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
fieldset { display: grid; gap: .55rem; margin: .7rem 0; padding: .8rem; border: 1px solid rgba(86,112,79,.2); border-radius: .6rem; }
legend { padding: 0 .3rem; font-weight: 700; }
.radio-label { display: flex; gap: .5rem; align-items: center; }
.field-error, .form-error { margin: 0; color: #8f1e1e; font-size: .9rem; }
.form-error { padding: .7rem; border-radius: .5rem; background: #fff0ee; font-weight: 700; }
.recurrence-confirmation { margin-top: .5rem; padding: .8rem; border: 1px solid #b9c5af; border-radius: .6rem; background: #f3f7ef; }
.recurrence-confirmation h4 { margin: 0; color: #243127; }
.recurrence-confirmation p { margin-bottom: 0; color: #50634d; }
.muted { margin: 0; color: #50634d; }
.modal-actions { display: flex; justify-content: end; gap: .6rem; margin-top: 1rem; }
.cancel-button, .confirm-button { padding: .6rem .8rem; border: 1px solid #395330; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.cancel-button { background: transparent; color: #395330; }
.confirm-button { background: #395330; color: #fffdf8; }
button:disabled { cursor: wait; opacity: .55; }
</style>
