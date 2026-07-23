<script setup lang="ts">
import { ref } from 'vue';
import type { RecipeStepInput } from '@/utils/recipes';

const props = defineProps<{ modelValue: RecipeStepInput[]; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: RecipeStepInput[]] }>();
const draggedIndex = ref<number | null>(null);

function update(index: number, field: keyof RecipeStepInput, value: string | number | null): void {
  emit('update:modelValue', props.modelValue.map((step, itemIndex) =>
    itemIndex === index ? { ...step, [field]: value } : step));
}

function move(index: number, offset: number): void {
  const target = index + offset;
  if (target < 0 || target >= props.modelValue.length) return;
  const steps = [...props.modelValue];
  [steps[index], steps[target]] = [steps[target]!, steps[index]!];
  emit('update:modelValue', steps);
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, { instruction: '', duration_minutes: null }]);
}

function remove(index: number): void {
  if (props.modelValue.length === 1) return;
  emit('update:modelValue', props.modelValue.filter((_, itemIndex) => itemIndex !== index));
}

function drop(index: number): void {
  if (draggedIndex.value === null || draggedIndex.value === index) return;
  const steps = [...props.modelValue];
  const [step] = steps.splice(draggedIndex.value, 1);
  if (step) steps.splice(index, 0, step);
  emit('update:modelValue', steps);
  draggedIndex.value = null;
}
</script>

<template>
  <div class="step-list">
    <article v-for="(step, index) in modelValue" :key="index" class="step-row" draggable="true" @dragstart="draggedIndex = index" @dragover.prevent @drop.prevent="drop(index)">
      <div class="row-heading">
        <strong>Étape {{ index + 1 }}</strong>
        <div class="step-actions">
          <button type="button" :disabled="disabled || index === 0" aria-label="Monter l'étape" @click="move(index, -1)">↑</button>
          <button type="button" :disabled="disabled || index === modelValue.length - 1" aria-label="Descendre l'étape" @click="move(index, 1)">↓</button>
          <button v-if="modelValue.length > 1" type="button" class="remove-button" :disabled="disabled" @click="remove(index)">Supprimer</button>
        </div>
      </div>
      <label :for="'step-instruction-' + index">Instruction</label>
      <textarea :id="'step-instruction-' + index" rows="3" :value="step.instruction" :disabled="disabled" @input="update(index, 'instruction', ($event.target as HTMLTextAreaElement).value)" />
      <label :for="'step-duration-' + index">Durée (minutes, facultatif)</label>
      <input :id="'step-duration-' + index" type="number" min="0" :value="step.duration_minutes ?? ''" :disabled="disabled" @input="update(index, 'duration_minutes', ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value))" />
    </article>
    <button type="button" class="secondary-button" :disabled="disabled" @click="add">+ Ajouter une étape</button>
  </div>
</template>

<style scoped>
.step-list { display: grid; gap: .8rem; }
.step-row { display: grid; gap: .45rem; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; background: rgba(247,251,243,.6); }
.row-heading, .step-actions { display: flex; align-items: center; gap: .4rem; }
.row-heading { justify-content: space-between; }
label { font-weight: 700; }
textarea, input { width: 100%; box-sizing: border-box; padding: .65rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.step-actions button, .secondary-button { padding: .45rem .6rem; border: 1px solid #395330; border-radius: .45rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }
.remove-button { border-color: #8f1e1e !important; color: #8f1e1e !important; font-size: .8rem !important; }
.secondary-button { width: fit-content; font-weight: 700; }
button:disabled { cursor: wait; opacity: .5; }
@media (max-width: 34rem) { .row-heading { align-items: flex-start; flex-direction: column; } }
</style>
