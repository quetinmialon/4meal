<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { createCookbook } from '@/utils/cookbooks';

const router = useRouter();
const authStore = useAuthStore();
const form = reactive({ name: '' });
const nameError = ref('');
const globalError = ref('');
const isSubmitting = ref(false);
const errorSummary = ref<HTMLElement | null>(null);

function validate(): boolean {
  nameError.value = form.name.trim() === '' ? 'Le nom du cookbook est requis.' : '';
  return nameError.value === '';
}

function clearErrors(): void {
  nameError.value = '';
  globalError.value = '';
}

async function handleSubmit(): Promise<void> {
  clearErrors();

  if (!validate()) {
    await nextTick();
    document.getElementById('cookbook-name-input')?.focus();
    return;
  }

  isSubmitting.value = true;

  const result = await createCookbook(form.name, authStore.tokenType, authStore.accessToken);

  if (!result.ok) {
    globalError.value = result.message;
    nameError.value = result.fieldErrors.name ?? '';
    isSubmitting.value = false;
    await nextTick();
    errorSummary.value?.focus();
    return;
  }

  await router.push({ name: 'cookbook', params: { id: result.cookbook.id } });
}

function handleInput(): void {
  clearErrors();
}
</script>

<template>
  <form class="cookbook-form" novalidate @submit.prevent="handleSubmit">
    <fieldset :disabled="isSubmitting">
      <div
        v-if="globalError"
        ref="errorSummary"
        class="error-summary"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
      >
        {{ globalError }}
      </div>

      <label for="cookbook-name-input">Nom du cookbook</label>
      <input
        id="cookbook-name-input"
        v-model="form.name"
        name="name"
        type="text"
        autocomplete="off"
        maxlength="255"
        :aria-invalid="nameError ? 'true' : 'false'"
        :aria-describedby="nameError ? 'cookbook-name-error' : undefined"
        @input="handleInput"
      />
      <p v-if="nameError" id="cookbook-name-error" class="field-error" role="alert">
        {{ nameError }}
      </p>

      <button type="submit">
        {{ isSubmitting ? 'Creation...' : 'Creer le cookbook' }}
      </button>
    </fieldset>
  </form>
</template>

<style scoped>
.cookbook-form { margin-top: 1.5rem; }
fieldset { display: grid; gap: 0.65rem; padding: 0; border: 0; }
label { font-weight: 700; }
input { padding: 0.8rem 0.9rem; border: 1px solid #b9c5af; border-radius: 0.65rem; background: #fffdf8; font: inherit; }
button { width: fit-content; margin-top: 0.5rem; padding: 0.8rem 1.1rem; border: 0; border-radius: 0.65rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.65; }
.error-summary, .field-error { color: #8f1e1e; }
.error-summary { padding: 0.75rem; border-radius: 0.5rem; background: #fff0ee; }
.field-error { margin: 0; font-size: 0.9rem; }
</style>
