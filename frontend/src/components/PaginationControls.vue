<script setup lang="ts">
type Pagination = {
  current_page: number;
  last_page: number;
  has_more_pages: boolean;
};

defineProps<{ pagination: Pagination; disabled?: boolean }>();
const emit = defineEmits<{ change: [page: number] }>();
</script>

<template>
  <nav class="pagination" aria-label="Pagination des recettes">
    <button type="button" :disabled="disabled || pagination.current_page === 1" @click="emit('change', pagination.current_page - 1)">Précédent</button>
    <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
    <button type="button" :disabled="disabled || !pagination.has_more_pages" @click="emit('change', pagination.current_page + 1)">Suivant</button>
  </nav>
</template>

<style scoped>
.pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1.5rem; color: #50634d; font-size: .9rem; }
.pagination button { padding: .55rem .75rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: .45; }
</style>
