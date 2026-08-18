<script setup lang="ts">
import { ref, watch } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { setRecipeFavorite } from '@/utils/recipes';

const props = defineProps<{
  recipeId: string;
  isFavorite?: boolean;
  iconOnly?: boolean;
}>();

const isFavorite = ref(props.isFavorite ?? false);
const isSaving = ref(false);
const errorMessage = ref('');

watch(() => props.isFavorite, (value) => {
  if (!isSaving.value) isFavorite.value = value ?? false;
});

async function toggleFavorite(): Promise<void> {
  if (isSaving.value) return;

  const previousValue = isFavorite.value;
  const nextValue = !previousValue;
  isFavorite.value = nextValue;
  errorMessage.value = '';
  isSaving.value = true;
  const authStore = useAuthStore();

  const result = await setRecipeFavorite(
    props.recipeId,
    nextValue,
    authStore.tokenType,
    authStore.accessToken,
  );

  if (!result.ok) {
    isFavorite.value = previousValue;
    errorMessage.value = result.message;
  }

  isSaving.value = false;
}
</script>

<template>
  <div class="favorite-control">
    <button
      type="button"
      class="favorite-button"
      :class="{ active: isFavorite, 'icon-only': props.iconOnly }"
      :disabled="isSaving"
      :aria-pressed="isFavorite"
      :aria-label="isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
      @click="toggleFavorite"
    >
      <span aria-hidden="true">{{ isFavorite ? '★' : '☆' }}</span>
      <span v-if="!props.iconOnly">{{ isFavorite ? 'Favori' : 'Ajouter aux favoris' }}</span>
    </button>
    <p v-if="errorMessage" class="favorite-error" role="alert">{{ errorMessage }}</p>
  </div>
</template>

<style scoped>
.favorite-control { display: flex; flex-wrap: wrap; align-items: center; gap: .55rem; }
.favorite-button { min-height: 2.4rem; padding: .5rem .7rem; border: 1px solid var(--color-border-strong); border-radius: var(--radius-md); background: transparent; color: var(--color-text-secondary); font: inherit; font-size: .875rem; font-weight: 700; cursor: pointer; transition: border-color .16s ease, background-color .16s ease, color .16s ease; }
.favorite-button:hover, .favorite-button:focus-visible { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-accent); }
.favorite-button.active { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-accent); }
.favorite-button.icon-only { width: 2.4rem; height: 2.4rem; padding: .25rem; font-size: 1.35rem; line-height: 1; }
.favorite-button:disabled { cursor: wait; opacity: .65; }
.favorite-error { flex-basis: 100%; margin: 0; color: var(--color-danger); font-size: .85rem; }
</style>
