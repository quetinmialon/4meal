<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import RecipeIngredientList from '@/components/RecipeIngredientList.vue';
import RecipeImageField from '@/components/RecipeImageField.vue';
import RecipeStepList from '@/components/RecipeStepList.vue';
import { useAuthStore } from '@/stores/auth';
import type { Cookbook } from '@/utils/cookbooks';
import { fetchCookbooks } from '@/utils/cookbooks';
import { createRecipe, type RecipeIngredientInput, type RecipeInput, type RecipeStepInput } from '@/utils/recipes';

const router = useRouter();
const authStore = useAuthStore();
const destination = ref<'personal' | 'cookbook'>('personal');
const selectedCookbook = ref('');
const cookbooks = ref<Cookbook[]>([]);
const cookbookLoading = ref(false);
const cookbookError = ref('');
const isSubmitting = ref(false);
const globalError = ref('');
const fieldErrors = ref<Record<string, string>>({});
const errorSummary = ref<HTMLElement | null>(null);
const tagDraft = ref('');
const blankIngredient = (): RecipeIngredientInput => ({ name: '', quantity: null, unit: '', preparation: '', is_optional: false, group_name: '' });
const blankStep = (): RecipeStepInput => ({ instruction: '', duration_minutes: null });
const form = reactive<RecipeInput>({ title: '', prep_time_minutes: null, cook_time_minutes: null, servings: null, source: '', cookbook_id: null, ingredients: [blankIngredient()], steps: [blankStep()], tags: [], image: null });

function numberValue(event: Event): number | null { const value = (event.target as HTMLInputElement).value; return value === '' ? null : Number(value); }
function errorFor(key: string): string { return fieldErrors.value[key] ?? ''; }
function addTag(): void { const tag = tagDraft.value.trim(); if (tag !== '' && !form.tags.includes(tag)) form.tags.push(tag); tagDraft.value = ''; }
function removeTag(index: number): void { form.tags.splice(index, 1); }
function clearErrors(): void { globalError.value = ''; fieldErrors.value = {}; }
function validate(): boolean {
  const errors: Record<string, string> = {};
  if (form.title.trim() === '') errors.title = 'Le titre est requis.';
  if (destination.value === 'cookbook' && selectedCookbook.value === '') errors.cookbook_id = 'Choisissez un cookbook.';
  if (form.ingredients.length === 0) errors.ingredients = 'Ajoutez au moins un ingrédient.';
  else form.ingredients.forEach((ingredient, index) => { if (ingredient.name.trim() === '') errors['ingredients.' + index + '.name'] = 'Le nom est requis.'; });
  if (form.steps.length === 0) errors.steps = 'Ajoutez au moins une étape.';
  else form.steps.forEach((step, index) => { if (step.instruction.trim() === '') errors['steps.' + index + '.instruction'] = 'L’instruction est requise.'; });
  if (form.prep_time_minutes !== null && form.prep_time_minutes < 0) errors.prep_time_minutes = 'La durée ne peut pas être négative.';
  if (form.cook_time_minutes !== null && form.cook_time_minutes < 0) errors.cook_time_minutes = 'La durée ne peut pas être négative.';
  if (form.servings !== null && form.servings < 1) errors.servings = 'Le nombre de portions doit être supérieur à zéro.';
  fieldErrors.value = errors;
  return Object.keys(errors).length === 0;
}
async function handleSubmit(): Promise<void> {
  clearErrors();
  if (!validate()) { await nextTick(); document.getElementById('recipe-title-input')?.focus(); return; }
  isSubmitting.value = true;
  const result = await createRecipe({ ...form, cookbook_id: destination.value === 'cookbook' ? selectedCookbook.value : null }, authStore.tokenType, authStore.accessToken);
  if (!result.ok) { globalError.value = result.message; fieldErrors.value = result.fieldErrors; isSubmitting.value = false; await nextTick(); errorSummary.value?.focus(); return; }
  await router.push({ name: 'dashboard' });
}
async function loadCookbooks(): Promise<void> {
  cookbookLoading.value = true; cookbookError.value = '';
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken);
  if (result.ok) cookbooks.value = result.data; else cookbookError.value = result.message;
  cookbookLoading.value = false;
}
onMounted(() => { void loadCookbooks(); });
</script>

<template>
  <form class="recipe-form" novalidate @submit.prevent="handleSubmit">
    <fieldset :disabled="isSubmitting">
      <div v-if="globalError" ref="errorSummary" class="error-summary" role="alert" aria-live="assertive" tabindex="-1">{{ globalError }}</div>

      <section class="form-section" aria-labelledby="general-information-title">
        <h2 id="general-information-title">Informations générales</h2>
        <div class="field-group">
          <label for="recipe-title-input">Titre</label>
          <input id="recipe-title-input" v-model="form.title" maxlength="255" :aria-invalid="errorFor('title') ? 'true' : 'false'" aria-describedby="recipe-title-error" />
          <p v-if="errorFor('title')" id="recipe-title-error" class="field-error" role="alert">{{ errorFor('title') }}</p>
        </div>
        <RecipeImageField :model-value="form.image ?? null" :disabled="isSubmitting" @update:model-value="form.image = $event" />
        <div class="field-group">
          <label for="recipe-source-input">Source (facultatif)</label>
          <input id="recipe-source-input" v-model="form.source" maxlength="2048" />
        </div>
      </section>

      <section class="form-section" aria-labelledby="timing-title">
        <h2 id="timing-title">Temps et portions</h2>
        <div class="form-grid">
          <div class="field-group"><label for="recipe-prep-time-input">Temps de préparation (minutes)</label><input id="recipe-prep-time-input" type="number" min="0" :value="form.prep_time_minutes ?? ''" :aria-invalid="errorFor('prep_time_minutes') ? 'true' : 'false'" aria-describedby="recipe-prep-time-error" @input="form.prep_time_minutes = numberValue($event)" /><p v-if="errorFor('prep_time_minutes')" id="recipe-prep-time-error" class="field-error" role="alert">{{ errorFor('prep_time_minutes') }}</p></div>
          <div class="field-group"><label for="recipe-cook-time-input">Temps de cuisson (minutes)</label><input id="recipe-cook-time-input" type="number" min="0" :value="form.cook_time_minutes ?? ''" :aria-invalid="errorFor('cook_time_minutes') ? 'true' : 'false'" aria-describedby="recipe-cook-time-error" @input="form.cook_time_minutes = numberValue($event)" /><p v-if="errorFor('cook_time_minutes')" id="recipe-cook-time-error" class="field-error" role="alert">{{ errorFor('cook_time_minutes') }}</p></div>
          <div class="field-group"><label for="recipe-servings-input">Portions</label><input id="recipe-servings-input" type="number" min="1" :value="form.servings ?? ''" :aria-invalid="errorFor('servings') ? 'true' : 'false'" aria-describedby="recipe-servings-error" @input="form.servings = numberValue($event)" /><p v-if="errorFor('servings')" id="recipe-servings-error" class="field-error" role="alert">{{ errorFor('servings') }}</p></div>
        </div>
      </section>

      <section class="form-section" aria-labelledby="ingredients-title">
        <h2 id="ingredients-title">Ingrédients</h2>
        <RecipeIngredientList v-model="form.ingredients" :disabled="isSubmitting" />
        <p v-if="errorFor('ingredients')" class="field-error" role="alert">{{ errorFor('ingredients') }}</p>
        <p v-for="(_, index) in form.ingredients" :key="'ingredient-error-' + index" class="field-error" role="alert">{{ errorFor('ingredients.' + index + '.name') }}</p>
      </section>

      <section class="form-section" aria-labelledby="steps-title">
        <h2 id="steps-title">Étapes</h2>
        <RecipeStepList v-model="form.steps" :disabled="isSubmitting" />
        <p v-if="errorFor('steps')" class="field-error" role="alert">{{ errorFor('steps') }}</p>
        <p v-for="(_, index) in form.steps" :key="'step-error-' + index" class="field-error" role="alert">{{ errorFor('steps.' + index + '.instruction') }}</p>
      </section>

      <section class="form-section" aria-labelledby="organization-title">
        <h2 id="organization-title">Organisation</h2>
        <fieldset class="destination-fieldset">
          <legend>Espace de la recette</legend>
          <label class="radio-label"><input v-model="destination" type="radio" value="personal" /> Mon espace personnel</label>
          <label class="radio-label"><input v-model="destination" type="radio" value="cookbook" /> Un cookbook</label>
          <label v-if="destination === 'cookbook'" for="recipe-cookbook-select">Cookbook de destination</label>
          <select v-if="destination === 'cookbook'" id="recipe-cookbook-select" v-model="selectedCookbook" :aria-invalid="errorFor('cookbook_id') ? 'true' : 'false'" aria-describedby="recipe-cookbook-error">
            <option value="">Sélectionner un cookbook</option><option v-for="cookbook in cookbooks" :key="cookbook.id" :value="cookbook.id">{{ cookbook.name }}</option>
          </select>
          <p v-if="cookbookLoading" role="status">Chargement des cookbooks...</p><p v-if="cookbookError" class="field-error" role="alert">{{ cookbookError }}</p><p v-if="errorFor('cookbook_id')" id="recipe-cookbook-error" class="field-error" role="alert">{{ errorFor('cookbook_id') }}</p>
        </fieldset>
        <div class="field-group"><label for="recipe-tags-input">Tags</label><div class="tag-entry"><input id="recipe-tags-input" v-model="tagDraft" placeholder="Ex. rapide" @keydown.enter.prevent="addTag" /><button type="button" class="secondary-button" @click="addTag">Ajouter</button></div></div>
        <div v-if="form.tags.length" class="tag-list" aria-label="Tags ajoutés"><span v-for="(tag, index) in form.tags" :key="tag" class="tag-chip">{{ tag }} <button type="button" :aria-label="'Supprimer ' + tag" @click="removeTag(index)">×</button></span></div>
      </section>

      <div class="form-actions"><button type="button" class="cancel-button" @click="router.push({ name: 'recipes' })">Annuler</button><button class="submit-button" type="submit">{{ isSubmitting ? 'Création...' : 'Créer la recette' }}</button></div>
    </fieldset>
  </form>
</template>

<style scoped>
.recipe-form { width: 100%; max-width: 60rem; margin: 1.5rem auto 0; padding: clamp(1rem, 3vw, 2rem); box-sizing: border-box; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
fieldset { display: grid; gap: 1.25rem; padding: 0; border: 0; }.form-section { display: grid; gap: .8rem; padding-top: 1.25rem; border-top: 1px solid rgba(86,112,79,.18); }.form-section:first-of-type { padding-top: 0; border-top: 0; }h2 { margin: 0; font-size: 1.4rem; }label, legend { font-weight: 700; }.field-group { display: grid; gap: .4rem; }input, select { box-sizing: border-box; width: 100%; padding: .75rem; border: 1px solid #b9c5af; border-radius: .6rem; background: #fffdf8; font: inherit; }.form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }.destination-fieldset { display: grid; gap: .5rem; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; }.radio-label { display: flex; align-items: center; gap: .5rem; font-weight: 400; }.radio-label input { width: auto; }.tag-entry, .form-actions { display: flex; gap: .5rem; }.tag-entry input { flex: 1; }.tag-list { display: flex; flex-wrap: wrap; gap: .5rem; }.tag-chip { padding: .35rem .55rem; border-radius: 999px; background: #edf4e8; color: #395330; }.tag-chip button { border: 0; background: transparent; color: inherit; cursor: pointer; font-size: 1rem; }.submit-button, .secondary-button, .cancel-button { width: fit-content; padding: .75rem 1rem; border: 1px solid #395330; border-radius: .6rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }.secondary-button, .cancel-button { background: transparent; color: #395330; }.form-actions { justify-content: end; padding-top: 1.25rem; border-top: 1px solid rgba(86,112,79,.18); }.error-summary, .field-error { color: #8f1e1e; }.error-summary { padding: .75rem; border-radius: .5rem; background: #fff0ee; }.field-error { margin: 0; font-size: .9rem; }button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 42rem) { .form-grid { grid-template-columns: 1fr; }.form-actions { flex-direction: column-reverse; }.form-actions button { width: 100%; } }
</style>
