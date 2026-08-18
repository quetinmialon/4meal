<script setup lang="ts">
import { useId, ref, watch } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { setRecipeRating } from '@/utils/recipes';

const props = defineProps<{
  recipeId: string;
  personalRating?: number | null;
}>();

const emit = defineEmits<{
  'update:personalRating': [value: number | null];
}>();

const maximumRating = 5;
const rating = ref(normalizeRating(props.personalRating));
const isSaving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const groupId = `recipe-rating-${useId()}`;

watch(() => props.personalRating, (value) => {
  if (!isSaving.value) rating.value = normalizeRating(value);
});

function normalizeRating(value: number | null | undefined): number | null {
  return value !== null && value !== undefined && value >= 1 && value <= maximumRating
    ? Math.round(value)
    : null;
}

async function updateRating(nextRating: number | null): Promise<void> {
  if (isSaving.value) return;

  const previousRating = rating.value;
  rating.value = nextRating;
  errorMessage.value = '';
  successMessage.value = '';
  isSaving.value = true;

  const authStore = useAuthStore();
  const result = await setRecipeRating(
    props.recipeId,
    nextRating,
    authStore.tokenType,
    authStore.accessToken,
  );

  if (!result.ok) {
    rating.value = previousRating;
    errorMessage.value = result.message;
  } else {
    rating.value = result.rating;
    emit('update:personalRating', result.rating);
    successMessage.value = nextRating === null ? 'Votre note a été supprimée.' : 'Votre note a été enregistrée.';
  }

  isSaving.value = false;
}
</script>

<template>
  <div class="rating-control">
    <fieldset :disabled="isSaving" :aria-describedby="`${groupId}-hint`">
      <legend>Votre note</legend>
      <div class="rating-options">
        <span v-for="value in maximumRating" :key="value" class="rating-option">
          <input
            :id="`${groupId}-${value}`"
            type="radio"
            :name="groupId"
            :value="value"
            :checked="rating === value"
            :aria-label="`${value} sur ${maximumRating}`"
            @change="updateRating(value)"
          />
          <label :for="`${groupId}-${value}`">
            <span aria-hidden="true">{{ rating !== null && value <= rating ? '★' : '☆' }}</span>
            <span class="visually-hidden">{{ value }} sur {{ maximumRating }}</span>
          </label>
        </span>
      </div>
    </fieldset>
    <p :id="`${groupId}-hint`" class="rating-hint">
      {{ rating === null ? 'Aucune note' : `${rating} sur ${maximumRating}` }}
    </p>
    <button v-if="rating !== null" type="button" class="clear-rating" :disabled="isSaving" @click="updateRating(null)">
      Supprimer ma note
    </button>
    <p v-if="errorMessage" class="rating-error" role="alert">{{ errorMessage }}</p>
    <p v-if="successMessage" class="rating-success" role="status" aria-live="polite">{{ successMessage }}</p>
  </div>
</template>

<style scoped>
.rating-control { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem .7rem; }
fieldset { display: flex; align-items: center; gap: .5rem; margin: 0; padding: 0; border: 0; }
legend { margin-right: .15rem; color: var(--color-primary); font: inherit; font-weight: 700; }
.rating-options { display: inline-flex; gap: .05rem; }
.rating-option { display: inline-flex; }
.rating-option input { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; clip-path: inset(50%); }
.rating-option label { padding: .1rem .2rem; border-radius: var(--radius-sm); color: var(--color-accent); font-size: 1.45rem; line-height: 1; cursor: pointer; }
.rating-option label:hover, .rating-option input:focus-visible + label { outline: 2px solid var(--color-focus); outline-offset: 2px; }
fieldset:disabled label { cursor: wait; opacity: .6; }
.rating-hint, .rating-success, .rating-error { flex-basis: 100%; margin: 0; font-size: .85rem; }
.rating-hint { color: var(--color-text-muted); }
.rating-success { color: var(--color-success); }
.rating-error { color: var(--color-danger); }
.clear-rating { padding: .25rem .45rem; border: 1px solid var(--color-border-strong); border-radius: var(--radius-sm); background: transparent; color: var(--color-primary); font: inherit; font-size: .8rem; cursor: pointer; }
.clear-rating:disabled { cursor: wait; opacity: .6; }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
</style>
