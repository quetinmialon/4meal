<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

type FieldName = 'email' | 'token' | 'password' | 'passwordConfirmation';

const route = useRoute();
const authStore = useAuthStore();
const fieldOrder: FieldName[] = ['email', 'token', 'password', 'passwordConfirmation'];
const queryValue = (name: string): string => (typeof route.query[name] === 'string' ? route.query[name] : '');

const form = reactive<Record<FieldName, string>>({
  email: queryValue('email'),
  token: queryValue('token'),
  password: '',
  passwordConfirmation: '',
});
const errors = reactive<Record<FieldName, string>>({
  email: '',
  token: '',
  password: '',
  passwordConfirmation: '',
});
const globalError = ref('');
const successMessage = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);

function validateField(field: FieldName): string {
  if (form[field] === '') {
    return field === 'passwordConfirmation' ? 'La confirmation du mot de passe est requise.' : 'Ce champ est requis.';
  }

  if (field === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    return 'Saisissez une adresse e-mail valide.';
  }

  if (field === 'password' && form.password.length < 8) {
    return 'Le mot de passe doit contenir au moins 8 caractères.';
  }

  if (field === 'passwordConfirmation' && form.passwordConfirmation !== form.password) {
    return 'Les mots de passe ne correspondent pas.';
  }

  return '';
}

function validateForm(): boolean {
  fieldOrder.forEach((field) => {
    errors[field] = validateField(field);
  });
  return fieldOrder.every((field) => errors[field] === '');
}

function clearFieldError(field: FieldName): void {
  errors[field] = '';
  globalError.value = '';
  if (field === 'password' && form.passwordConfirmation !== '') {
    errors.passwordConfirmation = validateField('passwordConfirmation');
  }
}

async function focusError(): Promise<void> {
  await nextTick();
  const field = fieldOrder.find((name) => errors[name] !== '');
  if (field !== undefined) {
    document.getElementById(`${field}-input`)?.focus();
    return;
  }
  formErrorSummary.value?.focus();
}

async function handleSubmit(): Promise<void> {
  globalError.value = '';
  successMessage.value = '';

  if (!validateForm()) {
    await focusError();
    return;
  }

  const result = await authStore.resetPassword(
    form.email,
    form.token,
    form.password,
    form.passwordConfirmation,
  );

  if (result.ok) {
    successMessage.value = 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.';
    return;
  }

  globalError.value = result.message;
  errors.email = result.fieldErrors.email ?? '';
  errors.token = result.fieldErrors.token ?? '';
  errors.password = result.fieldErrors.password ?? '';
  errors.passwordConfirmation = result.fieldErrors.password_confirmation ?? '';
  await focusError();
}
</script>

<template>
  <main class="auth-card">
    <p class="kicker">Sécurité</p>
    <h1>Nouveau mot de passe</h1>
    <p class="intro">Choisissez un nouveau mot de passe d'au moins 8 caractères.</p>

    <div v-if="successMessage" class="success-message" role="status" aria-live="polite">
      <p>{{ successMessage }}</p>
      <RouterLink :to="{ name: 'login' }">Se connecter</RouterLink>
    </div>

    <form v-else novalidate @submit.prevent="handleSubmit">
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

      <fieldset :disabled="authStore.status === 'loading'">
        <div v-for="field in fieldOrder" :key="field" class="field">
          <label :for="`${field}-input`">
            {{ field === 'email' ? 'Adresse e-mail' : field === 'token' ? 'Code de réinitialisation' : field === 'password' ? 'Nouveau mot de passe' : 'Confirmation du mot de passe' }}
          </label>
          <input
            :id="`${field}-input`"
            v-model="form[field]"
            :name="field"
            :type="field === 'email' ? 'email' : field === 'token' ? 'text' : 'password'"
            :autocomplete="field === 'email' ? 'email' : field === 'token' ? 'one-time-code' : 'new-password'"
            :inputmode="field === 'email' ? 'email' : undefined"
            :aria-invalid="errors[field] ? 'true' : 'false'"
            :aria-describedby="errors[field] ? `${field}-error` : undefined"
            @input="clearFieldError(field)"
          />
          <p v-if="errors[field]" :id="`${field}-error`" class="field-error" role="alert">{{ errors[field] }}</p>
        </div>

        <button type="submit">
          {{ authStore.status === 'loading' ? 'Réinitialisation...' : 'Réinitialiser le mot de passe' }}
        </button>
      </fieldset>
    </form>

    <RouterLink v-if="!successMessage" class="back-link" :to="{ name: 'login' }">Retour à la connexion</RouterLink>
  </main>
</template>

<style scoped>
.auth-card { max-width: 42rem; margin: 0 auto; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.kicker { margin: 0 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1; }
.intro { margin: 0.9rem 0 0; color: #50634d; line-height: 1.6; }
form { margin-top: 1.75rem; }
fieldset { display: grid; gap: 1rem; margin: 0; padding: 0; border: 0; }
.field { display: grid; gap: 0.45rem; }
label { font-weight: 700; }
input { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
button { width: fit-content; max-width: 100%; justify-self: center; margin-top: 0.25rem; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { margin-bottom: 1rem; padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
.success-message p { margin: 0; }
.success-message a, .back-link { display: inline-block; margin-top: 0.9rem; color: #2f4520; font-weight: 700; }
.back-link { margin-top: 1.5rem; }
</style>
