<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

type FieldName = 'currentPassword' | 'password' | 'passwordConfirmation';

const authStore = useAuthStore();
const fieldOrder: FieldName[] = ['currentPassword', 'password', 'passwordConfirmation'];

const form = reactive<Record<FieldName, string>>({
  currentPassword: '',
  password: '',
  passwordConfirmation: '',
});

const clientErrors = reactive<Record<FieldName, string>>({
  currentPassword: '',
  password: '',
  passwordConfirmation: '',
});

const apiErrors = reactive<Record<FieldName, string>>({
  currentPassword: '',
  password: '',
  passwordConfirmation: '',
});

const touched = reactive<Record<FieldName, boolean>>({
  currentPassword: false,
  password: false,
  passwordConfirmation: false,
});

const hasSubmitted = ref(false);
const globalError = ref('');
const successMessage = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);

function validateField(field: FieldName): string {
  if (form[field].length === 0) {
    return field === 'passwordConfirmation'
      ? 'La confirmation du mot de passe est requise.'
      : 'Ce champ est requis.';
  }

  if (field === 'password' && form.password.length < 8) {
    return 'Le mot de passe doit contenir au moins 8 caracteres.';
  }

  if (field === 'passwordConfirmation' && form.passwordConfirmation !== form.password) {
    return 'Les mots de passe ne correspondent pas.';
  }

  return '';
}

function visibleError(field: FieldName): string {
  return apiErrors[field] || clientErrors[field];
}

function validateAndStore(field: FieldName): void {
  clientErrors[field] = validateField(field);
}

function handleInput(field: FieldName): void {
  apiErrors[field] = '';
  globalError.value = '';

  if (touched[field] || hasSubmitted.value) {
    validateAndStore(field);
  }

  if (field === 'password' && (touched.passwordConfirmation || hasSubmitted.value)) {
    validateAndStore('passwordConfirmation');
  }
}

function handleBlur(field: FieldName): void {
  touched[field] = true;
  validateAndStore(field);
}

function validateForm(): boolean {
  for (const field of fieldOrder) {
    touched[field] = true;
    validateAndStore(field);
  }

  return fieldOrder.every((field) => visibleError(field) === '');
}

function resetApiErrors(): void {
  for (const field of fieldOrder) {
    apiErrors[field] = '';
  }
}

async function focusFirstError(): Promise<void> {
  await nextTick();
  const invalidField = fieldOrder.find((field) => visibleError(field) !== '');

  if (invalidField !== undefined) {
    document.getElementById(`${invalidField}-input`)?.focus();
    return;
  }

  formErrorSummary.value?.focus();
}

async function handleSubmit(): Promise<void> {
  hasSubmitted.value = true;
  successMessage.value = '';
  globalError.value = '';
  resetApiErrors();

  if (!validateForm()) {
    await focusFirstError();
    return;
  }

  const result = await authStore.changePassword(
    form.currentPassword,
    form.password,
    form.passwordConfirmation,
  );

  if (result.ok) {
    successMessage.value = 'Votre mot de passe a bien ete modifie. Vous pouvez vous reconnecter.';
    return;
  }

  globalError.value = result.message;
  apiErrors.currentPassword = result.fieldErrors.current_password ?? '';
  apiErrors.password = result.fieldErrors.password ?? '';
  apiErrors.passwordConfirmation = result.fieldErrors.password_confirmation ?? '';
  await focusFirstError();
}
</script>

<template>
  <main class="password-card">
    <p class="kicker">Securite</p>
    <h2>Modifier le mot de passe</h2>
    <p class="intro">Choisissez un nouveau mot de passe d'au moins 8 caracteres.</p>

    <div v-if="successMessage" class="success-message" role="status" aria-live="polite">
      <p>{{ successMessage }}</p>
      <RouterLink :to="{ name: 'login' }">Se reconnecter</RouterLink>
    </div>

    <form v-else class="password-form" novalidate @submit.prevent="handleSubmit">
      <fieldset :disabled="authStore.status === 'loading'">
        <div
          v-if="globalError"
          ref="formErrorSummary"
          class="error-summary"
          role="alert"
          aria-live="assertive"
          tabindex="-1"
        >
          {{ globalError }}
        </div>

        <div v-for="field in fieldOrder" :key="field" class="field">
          <label :for="`${field}-input`">
            {{
              field === 'currentPassword'
                ? 'Ancien mot de passe'
                : field === 'password'
                  ? 'Nouveau mot de passe'
                  : 'Confirmation du mot de passe'
            }}
          </label>
          <input
            :id="`${field}-input`"
            v-model="form[field]"
            :name="field"
            type="password"
            :autocomplete="field === 'currentPassword' ? 'current-password' : 'new-password'"
            :aria-invalid="visibleError(field) ? 'true' : 'false'"
            :aria-describedby="visibleError(field) ? `${field}-error` : undefined"
            @blur="handleBlur(field)"
            @input="handleInput(field)"
          />
          <p v-if="visibleError(field)" :id="`${field}-error`" class="field-error" role="alert">
            {{ visibleError(field) }}
          </p>
        </div>

        <button type="submit">
          {{ authStore.status === 'loading' ? 'Modification...' : 'Modifier le mot de passe' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>

<style scoped>
.password-card {
  margin: 0 auto;
  max-width: 42rem;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.92);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}

.kicker {
  margin: 0 0 0.35rem;
  color: #6b7b57;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

h2 { margin: 0 0 0.75rem; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1; }
.intro { margin: 0; color: #50634d; line-height: 1.6; }
.password-form { margin-top: 1.75rem; }
fieldset { display: grid; gap: 1.25rem; margin: 0; padding: 0; border: 0; }
.field { display: grid; gap: 0.45rem; }
label { font-weight: 700; }
input { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
button { margin-top: 0.25rem; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
.success-message p { margin: 0; }
.success-message a { display: inline-block; margin-top: 0.75rem; color: #2f4520; font-weight: 700; }
</style>
