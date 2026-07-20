<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

type FieldName = 'email' | 'password';

const router = useRouter();
const authStore = useAuthStore();

const fieldOrder: FieldName[] = ['email', 'password'];

const form = reactive({
  email: '',
  password: '',
});

const touched = reactive<Record<FieldName, boolean>>({
  email: false,
  password: false,
});

const clientErrors = reactive<Record<FieldName, string>>({
  email: '',
  password: '',
});

const apiErrors = reactive<Record<FieldName, string>>({
  email: '',
  password: '',
});

const hasSubmitted = ref(false);
const globalError = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);

function normalizeEmail(value: string): string {
  return value.trim().toLowerCase();
}

function validateField(field: FieldName): string {
  switch (field) {
    case 'email': {
      const value = form.email.trim();

      if (value.length === 0) {
        return "L'adresse e-mail est requise.";
      }

      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!emailPattern.test(value)) {
        return "Saisissez une adresse e-mail valide.";
      }

      return '';
    }

    case 'password': {
      if (form.password.length === 0) {
        return 'Le mot de passe est requis.';
      }

      return '';
    }
  }
}

function visibleError(field: FieldName): string {
  return apiErrors[field] || clientErrors[field];
}

function validateAndStore(field: FieldName): void {
  clientErrors[field] = validateField(field);
}

function clearApiError(field: FieldName): void {
  apiErrors[field] = '';
}

function handleBlur(field: FieldName): void {
  touched[field] = true;
  validateAndStore(field);
}

function handleInput(field: FieldName): void {
  clearApiError(field);
  globalError.value = '';

  if (touched[field] || hasSubmitted.value) {
    validateAndStore(field);
  }
}

function validateForm(): boolean {
  for (const field of fieldOrder) {
    touched[field] = true;
    validateAndStore(field);
  }

  return fieldOrder.every((field) => visibleError(field) === '');
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

function resetApiErrors(): void {
  for (const field of fieldOrder) {
    apiErrors[field] = '';
  }
}

async function handleSubmit(): Promise<void> {
  hasSubmitted.value = true;
  resetApiErrors();
  globalError.value = '';

  if (!validateForm()) {
    await focusFirstError();
    return;
  }

  const result = await authStore.login({
    email: normalizeEmail(form.email),
    password: form.password,
  });

  if (result.ok) {
    await router.push({
      name: 'dashboard',
    });

    return;
  }

  globalError.value = result.message;
  apiErrors.email = result.fieldErrors.email ?? '';
  apiErrors.password = result.fieldErrors.password ?? '';

  await focusFirstError();
}
</script>

<template>
  <main class="login-card">
    <p class="kicker">Connexion</p>
    <h2>Acceder a votre espace</h2>
    <p class="intro">
      Connectez-vous avec votre adresse e-mail et votre mot de passe pour retrouver votre espace.
    </p>

    <form class="login-form" novalidate @submit.prevent="handleSubmit">
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
        <label class="field" for="email-input">
          <span>Adresse e-mail</span>
          <input
            id="email-input"
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            inputmode="email"
            :aria-invalid="visibleError('email') ? 'true' : 'false'"
            :aria-describedby="visibleError('email') ? 'email-error' : undefined"
            @blur="handleBlur('email')"
            @input="handleInput('email')"
          />
        </label>
        <p v-if="visibleError('email')" id="email-error" class="field-error" role="alert">
          {{ visibleError('email') }}
        </p>

        <label class="field" for="password-input">
          <span>Mot de passe</span>
          <input
            id="password-input"
            v-model="form.password"
            type="password"
            name="password"
            autocomplete="current-password"
            :aria-invalid="visibleError('password') ? 'true' : 'false'"
            :aria-describedby="visibleError('password') ? 'password-error' : undefined"
            @blur="handleBlur('password')"
            @input="handleInput('password')"
          />
        </label>
        <p v-if="visibleError('password')" id="password-error" class="field-error" role="alert">
          {{ visibleError('password') }}
        </p>

        <button type="submit">
          {{ authStore.status === 'loading' ? 'Connexion...' : 'Se connecter' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>

<style scoped>
.login-card {
  margin: 0 auto;
  max-width: 34rem;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.92);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}

.kicker {
  margin: 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #6b7b57;
}

h2 {
  margin: 0;
  font-size: clamp(1.9rem, 4vw, 2.8rem);
  line-height: 1;
}

.intro {
  margin: 0.9rem 0 0;
  max-width: 28rem;
  line-height: 1.6;
  color: #50634d;
}

.login-form {
  margin-top: 1.75rem;
}

fieldset {
  margin: 0;
  padding: 0;
  border: 0;
}

.field {
  display: block;
  margin-top: 1rem;
}

.field span {
  display: inline-block;
  margin-bottom: 0.45rem;
  font-weight: 700;
}

input {
  width: 100%;
  padding: 0.9rem 1rem;
  border: 1px solid #c4cfb8;
  border-radius: 0.95rem;
  background: #fffdf8;
  color: #243127;
  font: inherit;
}

input:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 2px;
}

input[aria-invalid='true'] {
  border-color: #b94848;
}

.field-error,
.error-summary {
  margin: 0.6rem 0 0;
  color: #8d2727;
  line-height: 1.5;
}

.error-summary {
  padding: 0.95rem 1rem;
  border: 1px solid rgba(185, 72, 72, 0.26);
  border-radius: 1rem;
  background: #fff3f0;
}

button {
  width: 100%;
  margin-top: 1.5rem;
  padding: 0.95rem 1.25rem;
  border: 0;
  border-radius: 999px;
  background: #2f4520;
  color: #f7f4ee;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

button:disabled {
  cursor: wait;
  opacity: 0.8;
}

@media (max-width: 640px) {
  .login-card {
    padding: 1.5rem;
  }
}
</style>
