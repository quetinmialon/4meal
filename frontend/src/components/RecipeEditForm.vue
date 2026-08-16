<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import RecipeIngredientList from '@/components/RecipeIngredientList.vue';
import RecipeImageField from '@/components/RecipeImageField.vue';
import RecipeStepList from '@/components/RecipeStepList.vue';
import { useAuthStore } from '@/stores/auth';
import { updateRecipe, type Recipe, type RecipeIngredientInput, type RecipeInput, type RecipeStepInput } from '@/utils/recipes';

const props = defineProps<{ recipe: Recipe }>();
const emit = defineEmits<{ reload: [] }>();
const router = useRouter();
const authStore = useAuthStore();
const isSubmitting = ref(false);
const globalError = ref('');
const fieldErrors = ref<Record<string, string>>({});
const conflict = ref(false);
const errorSummary = ref<HTMLElement | null>(null);
const tagDraft = ref('');
function ingredientInput(ingredient: NonNullable<Recipe['ingredients']>[number]): RecipeIngredientInput { return { name: ingredient.name, quantity: ingredient.quantity, unit: ingredient.unit ?? '', preparation: ingredient.preparation ?? '', is_optional: ingredient.is_optional, group_name: ingredient.group_name ?? '' }; }
function stepInput(step: NonNullable<Recipe['steps']>[number]): RecipeStepInput { return { instruction: step.instruction, duration_minutes: step.duration_minutes }; }
const form = reactive<RecipeInput>({ title: props.recipe.title, description: props.recipe.description ?? '', prep_time_minutes: props.recipe.prep_time_minutes, cook_time_minutes: props.recipe.cook_time_minutes, servings: props.recipe.servings, source: props.recipe.source ?? '', cookbook_id: null, ingredients: (props.recipe.ingredients ?? []).map(ingredientInput), steps: (props.recipe.steps ?? []).map(stepInput), tags: (props.recipe.tags ?? []).map((tag) => tag.name), image: null });
function numberValue(event: Event): number | null { const value = (event.target as HTMLInputElement).value; return value === '' ? null : Number(value); }
function errorFor(key: string): string { return fieldErrors.value[key] ?? ''; }
function addTag(): void { const tag = tagDraft.value.trim(); if (tag !== '' && !form.tags.includes(tag)) form.tags.push(tag); tagDraft.value = ''; }
function removeTag(index: number): void { form.tags.splice(index, 1); }
function validate(): boolean {
  const errors: Record<string, string> = {};
  if (form.title.trim() === '') errors.title = 'Le titre est requis.';
  form.ingredients.forEach((ingredient, index) => { if (ingredient.name.trim() === '') errors['ingredients.' + index + '.name'] = 'Le nom est requis.'; });
  form.steps.forEach((step, index) => { if (step.instruction.trim() === '') errors['steps.' + index + '.instruction'] = 'L’instruction est requise.'; });
  if (form.prep_time_minutes !== null && form.prep_time_minutes < 0) errors.prep_time_minutes = 'La durée ne peut pas être négative.';
  if (form.cook_time_minutes !== null && form.cook_time_minutes < 0) errors.cook_time_minutes = 'La durée ne peut pas être négative.';
  if (form.servings !== null && form.servings < 1) errors.servings = 'Le nombre de portions doit être supérieur à zéro.';
  fieldErrors.value = errors;
  return Object.keys(errors).length === 0;
}
function clearErrors(): void { globalError.value = ''; fieldErrors.value = {}; conflict.value = false; }
async function handleSubmit(): Promise<void> {
  clearErrors();
  if (!validate()) { await nextTick(); document.getElementById('recipe-title-edit-input')?.focus(); return; }
  isSubmitting.value = true;
  const result = await updateRecipe(props.recipe.id, { ...form }, authStore.tokenType, authStore.accessToken);
  if (!result.ok) { globalError.value = result.message; fieldErrors.value = result.fieldErrors; conflict.value = result.conflict === true; isSubmitting.value = false; await nextTick(); errorSummary.value?.focus(); return; }
  await router.push({ name: 'recipe-detail', params: { id: result.recipe.id } });
}
function reload(): void { if (!isSubmitting.value) emit('reload'); }
</script>

<template>
  <form class="recipe-form" novalidate @submit.prevent="handleSubmit">
    <fieldset :disabled="isSubmitting">
      <div v-if="globalError" ref="errorSummary" class="error-summary" role="alert" aria-live="assertive" tabindex="-1">{{ globalError }}<button v-if="conflict" type="button" class="reload-button" @click="reload">Recharger la recette</button></div>

      <section class="form-section" aria-labelledby="edit-general-information-title">
        <h2 id="edit-general-information-title">Informations générales</h2>
        <div class="field-group"><label for="recipe-title-edit-input">Titre</label><input id="recipe-title-edit-input" v-model="form.title" maxlength="255" :aria-invalid="errorFor('title') ? 'true' : 'false'" aria-describedby="recipe-title-edit-error" /><p v-if="errorFor('title')" id="recipe-title-edit-error" class="field-error" role="alert">{{ errorFor('title') }}</p></div>
        <div class="field-group"><label for="recipe-description-edit-input">Description</label><textarea id="recipe-description-edit-input" v-model="form.description" rows="4" /></div>
        <RecipeImageField :model-value="form.image ?? null" :existing-image-url="props.recipe.image_url ?? null" :disabled="isSubmitting" @update:model-value="form.image = $event" />
        <div class="field-group"><label for="recipe-source-edit-input">Source</label><input id="recipe-source-edit-input" v-model="form.source" maxlength="2048" /></div>
      </section>

      <section class="form-section" aria-labelledby="edit-timing-title">
        <h2 id="edit-timing-title">Temps et portions</h2>
        <div class="form-grid">
          <div class="field-group"><label for="recipe-prep-time-edit-input">Temps de préparation (minutes)</label><input id="recipe-prep-time-edit-input" type="number" min="0" :value="form.prep_time_minutes ?? ''" :aria-invalid="errorFor('prep_time_minutes') ? 'true' : 'false'" aria-describedby="recipe-prep-time-edit-error" @input="form.prep_time_minutes = numberValue($event)" /><p v-if="errorFor('prep_time_minutes')" id="recipe-prep-time-edit-error" class="field-error" role="alert">{{ errorFor('prep_time_minutes') }}</p></div>
          <div class="field-group"><label for="recipe-cook-time-edit-input">Temps de cuisson (minutes)</label><input id="recipe-cook-time-edit-input" type="number" min="0" :value="form.cook_time_minutes ?? ''" :aria-invalid="errorFor('cook_time_minutes') ? 'true' : 'false'" aria-describedby="recipe-cook-time-edit-error" @input="form.cook_time_minutes = numberValue($event)" /><p v-if="errorFor('cook_time_minutes')" id="recipe-cook-time-edit-error" class="field-error" role="alert">{{ errorFor('cook_time_minutes') }}</p></div>
          <div class="field-group"><label for="recipe-servings-edit-input">Portions</label><input id="recipe-servings-edit-input" type="number" min="1" :value="form.servings ?? ''" :aria-invalid="errorFor('servings') ? 'true' : 'false'" aria-describedby="recipe-servings-edit-error" @input="form.servings = numberValue($event)" /><p v-if="errorFor('servings')" id="recipe-servings-edit-error" class="field-error" role="alert">{{ errorFor('servings') }}</p></div>
        </div>
        <p class="ownership-note">L’espace de la recette ne peut pas être modifié ici.</p>
      </section>

      <section class="form-section" aria-labelledby="edit-ingredients-title"><h2 id="edit-ingredients-title">Ingrédients</h2><RecipeIngredientList v-model="form.ingredients" :disabled="isSubmitting" /><p v-for="(_, index) in form.ingredients" :key="'ingredient-error-' + index" class="field-error" role="alert">{{ errorFor('ingredients.' + index + '.name') }}</p></section>
      <section class="form-section" aria-labelledby="edit-steps-title"><h2 id="edit-steps-title">Étapes</h2><RecipeStepList v-model="form.steps" :disabled="isSubmitting" /><p v-for="(_, index) in form.steps" :key="'step-error-' + index" class="field-error" role="alert">{{ errorFor('steps.' + index + '.instruction') }}</p></section>

      <section class="form-section" aria-labelledby="edit-organization-title">
        <h2 id="edit-organization-title">Organisation</h2>
        <p class="ownership-note">L’espace de la recette est conservé lors de la modification.</p>
        <div class="field-group"><label for="recipe-tags-edit-input">Tags</label><div class="tag-entry"><input id="recipe-tags-edit-input" v-model="tagDraft" placeholder="Ex. rapide" @keydown.enter.prevent="addTag" /><button type="button" class="secondary-button" @click="addTag">Ajouter</button></div></div>
        <div v-if="form.tags.length" class="tag-list" aria-label="Tags de la recette"><span v-for="(tag, index) in form.tags" :key="tag" class="tag-chip">{{ tag }} <button type="button" :aria-label="'Supprimer ' + tag" @click="removeTag(index)">×</button></span></div>
      </section>

      <div class="form-actions"><button type="button" class="cancel-button" :disabled="isSubmitting" @click="router.push({ name: 'recipe-detail', params: { id: props.recipe.id } })">Annuler</button><button class="submit-button" type="submit">{{ isSubmitting ? 'Enregistrement...' : 'Enregistrer les modifications' }}</button></div>
    </fieldset>
  </form>
</template>

<style scoped>
.recipe-form { width: 100%; max-width: 60rem; margin: 1.5rem auto 0; padding: clamp(1rem, 3vw, 2rem); box-sizing: border-box; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.92); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
fieldset { display: grid; gap: 1.25rem; padding: 0; border: 0; }.form-section { display: grid; gap: .8rem; padding-top: 1.25rem; border-top: 1px solid rgba(86,112,79,.18); }.form-section:first-of-type { padding-top: 0; border-top: 0; }h2 { margin: 0; font-size: 1.4rem; }label { font-weight: 700; }.field-group { display: grid; gap: .4rem; }input, textarea { box-sizing: border-box; width: 100%; padding: .75rem; border: 1px solid #b9c5af; border-radius: .6rem; background: #fffdf8; font: inherit; }.form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }.ownership-note { margin: .4rem 0; color: #50634d; font-size: .9rem; }.tag-entry, .form-actions { display: flex; gap: .5rem; }.tag-entry input { flex: 1; }.tag-list { display: flex; flex-wrap: wrap; gap: .5rem; }.tag-chip { padding: .35rem .55rem; border-radius: 999px; background: #edf4e8; color: #395330; }.tag-chip button { border: 0; background: transparent; color: inherit; cursor: pointer; font-size: 1rem; }.submit-button, .secondary-button, .cancel-button, .reload-button { width: fit-content; padding: .75rem 1rem; border: 1px solid #395330; border-radius: .6rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }.secondary-button, .cancel-button { background: transparent; color: #395330; }.form-actions { justify-content: end; padding-top: 1.25rem; border-top: 1px solid rgba(86,112,79,.18); }.error-summary, .field-error { color: #8f1e1e; }.error-summary { display: grid; gap: .7rem; padding: .75rem; border-radius: .5rem; background: #fff0ee; }.reload-button { border-color: #8f1e1e; background: transparent; color: #8f1e1e; }.field-error { margin: 0; font-size: .9rem; }button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 42rem) { .form-grid { grid-template-columns: 1fr; }.form-actions { flex-direction: column-reverse; }.form-actions button { width: 100%; } }
</style>
