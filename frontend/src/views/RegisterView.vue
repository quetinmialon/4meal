<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import { useAuthStore } from '@/stores/auth';
import { handleGoogleAuthCallback } from '@/utils/googleAuth';

type FieldName = 'name' | 'email' | 'password' | 'passwordConfirmation';

type RegisterErrorPayload = {
  success: false;
  error?: {
    code?: string;
    message?: string;
    details?: {
      fields?: Record<string, string[]>;
    };
  };
};

const router = useRouter();
const authStore = useAuthStore();

const fieldOrder: FieldName[] = ['name', 'email', 'password', 'passwordConfirmation'];

const form = reactive({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
});

const touched = reactive<Record<FieldName, boolean>>({
  name: false,
  email: false,
  password: false,
  passwordConfirmation: false,
});

const clientErrors = reactive<Record<FieldName, string>>({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
});

const apiErrors = reactive<Record<FieldName, string>>({
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
});

const isSubmitting = ref(false);
const hasSubmitted = ref(false);
const globalError = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);

function normalizeName(value: string): string {
  return value.trim();
}

function normalizeEmail(value: string): string {
  return value.trim().toLowerCase();
}

function validateField(field: FieldName): string {
  switch (field) {
    case 'name': {
      const value = normalizeName(form.name);

      if (value.length === 0) {
        return 'Le nom est requis.';
      }

      if (value.length < 2) {
        return 'Le nom doit contenir au moins 2 caracteres.';
      }

      if (value.length > 255) {
        return 'Le nom ne peut pas depasser 255 caracteres.';
      }

      return '';
    }

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

      if (form.password.length < 8) {
        return 'Le mot de passe doit contenir au moins 8 caracteres.';
      }

      return '';
    }

    case 'passwordConfirmation': {
      if (form.passwordConfirmation.length === 0) {
        return 'La confirmation du mot de passe est requise.';
      }

      if (form.passwordConfirmation !== form.password) {
        return 'Les mots de passe ne correspondent pas.';
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

  if (field === 'password' && (touched.passwordConfirmation || hasSubmitted.value)) {
    validateAndStore('passwordConfirmation');
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

function applyApiErrors(payload: RegisterErrorPayload): void {
  globalError.value = payload.error?.message ?? "Une erreur est survenue pendant l'inscription.";

  const fieldErrors = payload.error?.details?.fields;

  if (fieldErrors === undefined) {
    return;
  }

  const fieldMap: Record<string, FieldName> = {
    name: 'name',
    email: 'email',
    password: 'password',
    password_confirmation: 'passwordConfirmation',
  };

  for (const [apiField, messages] of Object.entries(fieldErrors)) {
    const field = fieldMap[apiField];
    const message = messages[0];

    if (field !== undefined && typeof message === 'string') {
      apiErrors[field] = message;
    }
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

  isSubmitting.value = true;

  try {
    const response = await fetch('/api/auth/register', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: normalizeName(form.name),
        email: normalizeEmail(form.email),
        password: form.password,
        password_confirmation: form.passwordConfirmation,
      }),
    });

    const payload = (await response.json().catch(() => null)) as RegisterErrorPayload | null;

    if (response.ok) {
      await router.push({
        name: 'register-success',
        query: {
          email: normalizeEmail(form.email),
        },
      });

      return;
    }

    if (payload?.success === false) {
      applyApiErrors(payload);
      await focusFirstError();
      return;
    }

    globalError.value = "Une erreur est survenue pendant l'inscription.";
    await focusFirstError();
  } catch {
    globalError.value = "Impossible de joindre le serveur. Reessayez dans un instant.";
    await focusFirstError();
  } finally {
    isSubmitting.value = false;
  }
}

async function handleGoogleCallback(): Promise<void> {
  const callback = await handleGoogleAuthCallback(
    new URLSearchParams(window.location.search),
    (params) => authStore.completeGoogleLogin(params),
  );

  if (!callback.handled || callback.result === undefined) {
    return;
  }

  await router.replace({ query: {} });

  if (callback.result.ok) {
    await router.push({ name: 'dashboard' });
    return;
  }

  globalError.value = callback.result.message;
  await focusFirstError();
}

onMounted(() => {
  void handleGoogleCallback();
});
</script>

<template>
  <main class="register-card">
    <div class="card-header">
      <p class="kicker">Inscription</p>
      <h2>Creer un compte</h2>
      <p class="intro">
        Renseignez vos informations pour creer votre compte. Nous vous indiquerons les champs a
        corriger si besoin.
      </p>
      <GoogleAuthButton />
      <div class="oauth-separator" aria-hidden="true"><span>ou</span></div>
    </div>

    <form class="register-form" novalidate :aria-busy="isSubmitting || authStore.status === 'loading'" @submit.prevent="handleSubmit">
      <fieldset :disabled="isSubmitting || authStore.status === 'loading'">
        <div v-if="globalError" ref="formErrorSummary" class="form-alert" role="alert" tabindex="-1">
          {{ globalError }}
        </div>

        <div class="field">
          <label for="name-input">Nom</label>
          <input
            id="name-input"
            v-model="form.name"
            name="name"
            type="text"
            autocomplete="name"
            :aria-invalid="visibleError('name') ? 'true' : 'false'"
            :aria-describedby="visibleError('name') ? 'name-error' : 'name-hint'"
            @blur="handleBlur('name')"
            @input="handleInput('name')"
          />
          <p id="name-hint" class="hint">Au moins 2 caracteres.</p>
          <p v-if="visibleError('name')" id="name-error" class="error" role="alert">
            {{ visibleError('name') }}
          </p>
        </div>

        <div class="field">
          <label for="email-input">Adresse e-mail</label>
          <input
            id="email-input"
            v-model="form.email"
            name="email"
            type="email"
            inputmode="email"
            autocomplete="email"
            :aria-invalid="visibleError('email') ? 'true' : 'false'"
            :aria-describedby="visibleError('email') ? 'email-error' : 'email-hint'"
            @blur="handleBlur('email')"
            @input="handleInput('email')"
          />
          <p id="email-hint" class="hint">Utilisez l'adresse e-mail que vous emploierez pour vous connecter.</p>
          <p v-if="visibleError('email')" id="email-error" class="error" role="alert">
            {{ visibleError('email') }}
          </p>
        </div>

        <div class="field">
          <label for="password-input">Mot de passe</label>
          <input
            id="password-input"
            v-model="form.password"
            name="password"
            type="password"
            autocomplete="new-password"
            :aria-invalid="visibleError('password') ? 'true' : 'false'"
            :aria-describedby="visibleError('password') ? 'password-error' : 'password-hint'"
            @blur="handleBlur('password')"
            @input="handleInput('password')"
          />
          <p id="password-hint" class="hint">Minimum 8 caracteres.</p>
          <p v-if="visibleError('password')" id="password-error" class="error" role="alert">
            {{ visibleError('password') }}
          </p>
        </div>

        <div class="field">
          <label for="passwordConfirmation-input">Confirmation du mot de passe</label>
          <input
            id="passwordConfirmation-input"
            v-model="form.passwordConfirmation"
            name="passwordConfirmation"
            type="password"
            autocomplete="new-password"
            :aria-invalid="visibleError('passwordConfirmation') ? 'true' : 'false'"
            :aria-describedby="
              visibleError('passwordConfirmation')
                ? 'passwordConfirmation-error'
                : 'passwordConfirmation-hint'
            "
            @blur="handleBlur('passwordConfirmation')"
            @input="handleInput('passwordConfirmation')"
          />
          <p id="passwordConfirmation-hint" class="hint">Doit etre identique au mot de passe.</p>
          <p
            v-if="visibleError('passwordConfirmation')"
            id="passwordConfirmation-error"
            class="error"
            role="alert"
          >
            {{ visibleError('passwordConfirmation') }}
          </p>
        </div>

        <button class="submit-button" type="submit">
          {{ isSubmitting ? 'Creation du compte...' : 'Creer mon compte' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>

<style scoped>
.register-card {
  margin: 0 auto;
  max-width: 42rem;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.92);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}

.card-header {
  margin-bottom: 1.75rem;
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
  margin: 0 0 0.75rem;
  font-size: clamp(1.9rem, 4vw, 2.8rem);
  line-height: 1;
}

.intro {
  margin: 0;
  max-width: 34rem;
  color: #50634d;
  line-height: 1.6;
}

.oauth-separator {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1.5rem;
  color: #60725d;
  text-align: center;
}

.oauth-separator::before,
.oauth-separator::after {
  flex: 1;
  height: 1px;
  background: #d5ddce;
  content: '';
}

.register-form fieldset {
  display: grid;
  gap: 1.25rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.field {
  display: grid;
  gap: 0.45rem;
}

label {
  font-weight: 700;
}

input {
  width: 100%;
  padding: 0.9rem 1rem;
  border: 1px solid #b4bead;
  border-radius: 0.95rem;
  background: #fffdfa;
  color: #243127;
  font: inherit;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

input:focus {
  outline: none;
  border-color: #54703d;
  box-shadow: 0 0 0 4px rgba(116, 144, 88, 0.18);
}

input[aria-invalid='true'] {
  border-color: #b64242;
  background: #fff8f6;
}

.hint {
  margin: 0;
  font-size: 0.95rem;
  color: #60725d;
}

.error {
  margin: 0;
  color: #ab2c2c;
  font-size: 0.95rem;
}

.form-alert {
  padding: 0.95rem 1rem;
  border: 1px solid rgba(171, 44, 44, 0.24);
  border-radius: 1rem;
  background: #fff4f2;
  color: #8f1e1e;
}

.submit-button {
  justify-self: start;
  min-width: 13rem;
  padding: 0.95rem 1.3rem;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(135deg, #4d6737 0%, #2d4020 100%);
  color: #fffdf9;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease;
  box-shadow: 0 14px 24px rgba(45, 64, 32, 0.18);
}

.submit-button:hover {
  transform: translateY(-1px);
}

.submit-button:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 3px;
}

.submit-button:disabled,
fieldset:disabled .submit-button {
  cursor: wait;
  opacity: 0.72;
  transform: none;
}

@media (max-width: 640px) {
  .register-card {
    padding: 1.5rem;
    border-radius: 1.2rem;
  }

  .submit-button {
    width: 100%;
    justify-self: stretch;
  }
}
</style>
