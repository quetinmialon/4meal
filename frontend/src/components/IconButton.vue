<script setup lang="ts">
withDefaults(defineProps<{ label: string; type?: 'button' | 'submit' | 'reset'; disabled?: boolean }>(), { type: 'button', disabled: false });
</script>

<template>
  <button class="icon-button" :type="type" :aria-label="label" :disabled="disabled"><slot aria-hidden="true" /></button>
</template>

<style scoped>
.icon-button { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; min-height: 2.5rem; padding: 0; border: 1px solid var(--app-border, #b9c5af); border-radius: .65rem; background: transparent; color: var(--app-text, #243127); cursor: pointer; }
.icon-button:focus-visible { outline: 3px solid color-mix(in srgb, var(--app-muted, #50634d) 40%, transparent); outline-offset: 2px; }
.icon-button:disabled { cursor: not-allowed; opacity: .5; }
</style>
