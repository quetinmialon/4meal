<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

withDefaults(defineProps<{ label: string; align?: 'start' | 'end' }>(), { align: 'end' });
const isOpen = ref(false);
const container = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const menuId = `dropdown-menu-${useId()}`;
function focusFirstItem(): void { void nextTick(() => container.value?.querySelector<HTMLElement>('[role="menuitem"]')?.focus()); }
function toggle(): void { isOpen.value = !isOpen.value; if (isOpen.value) focusFirstItem(); }
function close(restoreFocus = false): void { isOpen.value = false; if (restoreFocus) void nextTick(() => trigger.value?.focus()); }
function handleOutsideClick(event: PointerEvent): void { if (!container.value?.contains(event.target as Node)) close(); }
function handleKeydown(event: KeyboardEvent): void { if (isOpen.value && event.key === 'Escape') { event.preventDefault(); close(true); } }
onMounted(() => {
  document.addEventListener('pointerdown', handleOutsideClick);
  document.addEventListener('keydown', handleKeydown);
});
onBeforeUnmount(() => { document.removeEventListener('pointerdown', handleOutsideClick); document.removeEventListener('keydown', handleKeydown); });
</script>

<template>
  <div ref="container" class="dropdown-menu" :class="`dropdown-align-${align}`">
    <button ref="trigger" class="dropdown-trigger" type="button" aria-haspopup="menu" :aria-expanded="isOpen" :aria-controls="menuId" :aria-label="label" @click="toggle"><slot name="trigger">{{ label }}</slot></button>
    <div v-if="isOpen" :id="menuId" class="dropdown-panel" role="menu" :aria-label="label" @click="close(false)"><slot :close="close" /></div>
  </div>
</template>

<style scoped>
.dropdown-menu { position: relative; display: inline-block; }
.dropdown-trigger { display: inline-flex; align-items: center; justify-content: center; min-height: 2.5rem; padding: .45rem .75rem; border: 1px solid var(--app-border, #b9c5af); border-radius: .65rem; background: transparent; color: var(--app-text, #243127); font: inherit; font-weight: 700; cursor: pointer; }
.dropdown-trigger:focus-visible, .dropdown-panel a:focus-visible, .dropdown-panel button:focus-visible { outline: 3px solid color-mix(in srgb, var(--app-muted, #50634d) 40%, transparent); outline-offset: 2px; }
.dropdown-panel { position: absolute; z-index: 20; display: grid; gap: .25rem; min-width: 11rem; margin-top: .5rem; padding: .55rem; border: 1px solid var(--app-border, #b9c5af); border-radius: .75rem; background: var(--app-surface, #fffdf8); box-shadow: 0 12px 30px rgba(36, 49, 39, .14); }
.dropdown-align-end .dropdown-panel { right: 0; }
.dropdown-panel a, .dropdown-panel button { padding: .55rem .6rem; border: 0; border-radius: .45rem; background: transparent; color: var(--app-text, #243127); font: inherit; text-align: left; text-decoration: none; cursor: pointer; }
.dropdown-panel a:hover, .dropdown-panel button:hover { background: color-mix(in srgb, var(--app-border, #b9c5af) 45%, transparent); }
</style>
