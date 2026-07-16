<script setup lang="ts">
defineProps<{
  label: string;
}>();
</script>

<template>
  <strong class="status">{{ label }}</strong>
</template>

<style scoped>
.status {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border: 1px solid #8ca17b;
  border-radius: 999px;
  background: #f7fbf3;
  color: #395330;
  font-size: 0.95rem;
}
</style>
