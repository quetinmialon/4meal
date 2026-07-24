<script setup lang="ts">
defineProps<{ modelValue: string; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: string]; submit: [] }>();
</script>

<template>
  <form class="search-form" role="search" @submit.prevent="emit('submit')">
    <label for="recipe-search">Rechercher une recette</label>
    <div class="search-controls">
      <input
        id="recipe-search"
        :value="modelValue"
        type="search"
        name="q"
        autocomplete="off"
        placeholder="Titre, ingrédient, tag..."
        :disabled="disabled"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button type="submit" :disabled="disabled">Rechercher</button>
    </div>
  </form>
</template>

<style scoped>
.search-form { margin-top: 1.5rem; }
.search-form label { display: block; margin-bottom: .45rem; color: #395330; font-weight: 700; }
.search-controls { display: flex; gap: .6rem; }
.search-controls input { min-width: 0; flex: 1; padding: .75rem .85rem; border: 1px solid #b9c5af; border-radius: .6rem; background: #fffdf8; color: #243127; font: inherit; }
.search-controls button { padding: .7rem .9rem; border: 1px solid #395330; border-radius: .6rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.search-controls input:focus-visible, .search-controls button:focus-visible { outline: 3px solid rgba(86, 112, 79, .3); outline-offset: 2px; }
.search-controls button:disabled { cursor: wait; opacity: .55; }
@media (max-width: 38rem) { .search-controls { align-items: stretch; flex-direction: column; } }
</style>
