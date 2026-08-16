<script setup lang="ts">
import { computed, ref, useId } from 'vue';

import { useDialogFocus } from '@/utils/dialogFocus';

const props = withDefaults(defineProps<{
  modelValue: boolean;
  title: string;
  description?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: 'default' | 'danger';
}>(), { description: '', confirmLabel: 'Confirmer', cancelLabel: 'Annuler', tone: 'default' });

const emit = defineEmits<{ 'update:modelValue': [value: boolean]; confirm: []; cancel: [] }>();
const dialog = ref<HTMLElement | null>(null);
const isOpen = computed(() => props.modelValue);
const titleId = `confirm-dialog-title-${useId()}`;

function close(): void { emit('update:modelValue', false); emit('cancel'); }
function confirm(): void { emit('confirm'); }
useDialogFocus(dialog, isOpen, close);
</script>

<template>
  <div v-if="modelValue" class="dialog-backdrop" role="presentation" @click.self="close">
    <section ref="dialog" class="confirm-dialog" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1">
      <h2 :id="titleId">{{ title }}</h2>
      <p v-if="description">{{ description }}</p>
      <div class="confirm-dialog-actions">
        <button type="button" @click="close">{{ cancelLabel }}</button>
        <button type="button" :class="`confirm-${tone}`" @click="confirm">{{ confirmLabel }}</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dialog-backdrop { position: fixed; inset: 0; z-index: 30; display: grid; place-items: center; padding: 1rem; background: rgba(36, 49, 39, .48); }
.confirm-dialog { width: min(100%, 30rem); padding: 1.5rem; border: 1px solid var(--app-border, #b9c5af); border-radius: 1rem; background: var(--app-surface, #fffdf8); color: var(--app-text, #243127); box-shadow: 0 20px 60px rgba(36, 49, 39, .25); }
.confirm-dialog h2 { margin: 0; font-size: 1.25rem; }
.confirm-dialog p { margin: .75rem 0 0; color: var(--app-muted, #50634d); line-height: 1.5; }
.confirm-dialog-actions { display: flex; justify-content: end; gap: .6rem; margin-top: 1.25rem; }
.confirm-dialog button { padding: .65rem .9rem; border: 1px solid var(--app-border, #b9c5af); border-radius: .55rem; background: transparent; color: var(--app-text, #243127); font: inherit; font-weight: 700; cursor: pointer; }
.confirm-dialog .confirm-danger { border-color: #8f1e1e; background: #8f1e1e; color: #fff; }
.confirm-dialog button:focus-visible { outline: 3px solid color-mix(in srgb, var(--app-muted, #50634d) 40%, transparent); outline-offset: 2px; }
@media (max-width: 38rem) { .confirm-dialog-actions { flex-direction: column-reverse; } .confirm-dialog button { width: 100%; } }
</style>
