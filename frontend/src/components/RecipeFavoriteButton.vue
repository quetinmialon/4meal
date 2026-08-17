<script setup lang="ts">
import { ref, watch } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { setRecipeFavorite } from '@/utils/recipes';

const props = defineProps<{
  recipeId: string;
  isFavorite?: boolean;
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
      :class="{ active: isFavorite }"
      :disabled="isSaving"
      :aria-pressed="isFavorite"
      :aria-label="isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'"
      @click="toggleFavorite"
    >
      <span aria-hidden="true">{{ isFavorite ? '★' : '☆' }}</span>
      {{ isFavorite ? 'Favori' : 'Ajouter aux favoris' }}
    </button>
    <p v-if="errorMessage" class="favorite-error" role="alert">{{ errorMessage }}</p>
  </div>
</template>

<style scoped>
.favorite-control { display: flex; flex-wrap: wrap; align-items: center; gap: .55rem; }
.favorite-button { padding: .5rem .7rem; border: 1px solid #b9c5af; border-radius: .55rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.favorite-button.active { border-color: #d18a2e; background: #fff4d9; color: #8a5a16; }
.favorite-button:disabled { cursor: wait; opacity: .65; }
.favorite-error { flex-basis: 100%; margin: 0; color: #8f1e1e; font-size: .85rem; }
</style>
