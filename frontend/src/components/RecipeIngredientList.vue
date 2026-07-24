<script setup lang="ts">
import type { RecipeIngredientInput } from '@/utils/recipes';

const props = defineProps<{ modelValue: RecipeIngredientInput[]; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: RecipeIngredientInput[]] }>();

const blankIngredient = (): RecipeIngredientInput => ({
  name: '', quantity: null, unit: '', preparation: '', is_optional: false, group_name: '',
});

function update(index: number, field: keyof RecipeIngredientInput, value: string | number | boolean | null): void {
  emit('update:modelValue', props.modelValue.map((ingredient, itemIndex) =>
    itemIndex === index ? { ...ingredient, [field]: value } : ingredient));
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, blankIngredient()]);
}

function remove(index: number): void {
  if (props.modelValue.length === 1) return;
  emit('update:modelValue', props.modelValue.filter((_, itemIndex) => itemIndex !== index));
}
</script>

<template>
  <div class="ingredient-list">
    <article v-for="(ingredient, index) in modelValue" :key="index" class="ingredient-row">
      <div class="row-heading">
        <strong>Ingrédient {{ index + 1 }}</strong>
        <button v-if="modelValue.length > 1" type="button" class="remove-button" :disabled="disabled" @click="remove(index)">Supprimer</button>
      </div>
      <label :for="'ingredient-name-' + index">Nom</label>
      <input :id="'ingredient-name-' + index" :value="ingredient.name" :disabled="disabled" @input="update(index, 'name', ($event.target as HTMLInputElement).value)" />
      <div class="inline-fields">
        <div>
          <label :for="'ingredient-quantity-' + index">Quantité</label>
          <input :id="'ingredient-quantity-' + index" type="number" min="0" step="any" :value="ingredient.quantity ?? ''" :disabled="disabled" @input="update(index, 'quantity', ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))" />
        </div>
        <div>
          <label :for="'ingredient-unit-' + index">Unité</label>
          <input :id="'ingredient-unit-' + index" :value="ingredient.unit" :disabled="disabled" @input="update(index, 'unit', ($event.target as HTMLInputElement).value)" />
        </div>
      </div>
      <label :for="'ingredient-preparation-' + index">Préparation (facultatif)</label>
      <input :id="'ingredient-preparation-' + index" :value="ingredient.preparation" :disabled="disabled" @input="update(index, 'preparation', ($event.target as HTMLInputElement).value)" />
      <label class="checkbox-label">
        <input type="checkbox" :checked="ingredient.is_optional" :disabled="disabled" @change="update(index, 'is_optional', ($event.target as HTMLInputElement).checked)" />
        Facultatif
      </label>
    </article>
    <button type="button" class="secondary-button" :disabled="disabled" @click="add">+ Ajouter un ingrédient</button>
  </div>
</template>

<style scoped>
.ingredient-list { display: grid; gap: .8rem; }
.ingredient-row { display: grid; gap: .45rem; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; background: rgba(247,251,243,.6); }
.row-heading { display: flex; justify-content: space-between; align-items: center; }
.inline-fields { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; }
label { font-weight: 700; }
input:not([type="checkbox"]) { width: 100%; box-sizing: border-box; padding: .65rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.checkbox-label { display: flex; gap: .45rem; align-items: center; font-weight: 400; }
.remove-button, .secondary-button { width: fit-content; padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.remove-button { border-color: #8f1e1e; color: #8f1e1e; font-size: .85rem; }
button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 34rem) { .inline-fields { grid-template-columns: 1fr; } }
</style>
