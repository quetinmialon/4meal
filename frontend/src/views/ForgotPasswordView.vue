<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import forgotPasswordIllustration from '@/assets/auth-forgot-password.png';

const authStore = useAuthStore();
const form = reactive({ email: '' });
const emailError = ref('');
const globalError = ref('');
const successMessage = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);

function validate(): boolean {
  if (form.email.trim() === '') {
    emailError.value = "L'adresse e-mail est requise.";
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    emailError.value = 'Saisissez une adresse e-mail valide.';
  } else {
    emailError.value = '';
  }

  return emailError.value === '';
}

function clearErrors(): void {
  emailError.value = '';
  globalError.value = '';
}

async function handleSubmit(): Promise<void> {
  successMessage.value = '';
  clearErrors();

  if (!validate()) {
    await nextTick();
    document.getElementById('email-input')?.focus();
    return;
  }

  const result = await authStore.requestPasswordReset(form.email);

  if (result.ok) {
    successMessage.value =
      'Si cette adresse correspond à un compte, un email de réinitialisation a été envoyé.';
    return;
  }

  globalError.value = result.message;
  emailError.value = result.fieldErrors.email ?? '';
  await nextTick();
  formErrorSummary.value?.focus();
}
</script>

<template>
  <div class="auth-screen">
  <main class="auth-card">
    <p class="kicker">Sécurité</p>
    <h1>Mot de passe oublié</h1>
    <p class="intro">Indiquez votre adresse e-mail pour recevoir les instructions de réinitialisation.</p>

    <div v-if="successMessage" class="success-message" role="status" aria-live="polite">
      <p>{{ successMessage }}</p>
      <RouterLink :to="{ name: 'login' }">Retour à la connexion</RouterLink>
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
        <label class="field" for="email-input">
          <span>Adresse e-mail</span>
          <input
            id="email-input"
            v-model="form.email"
            name="email"
            type="email"
            autocomplete="email"
            inputmode="email"
            :aria-invalid="emailError ? 'true' : 'false'"
            :aria-describedby="emailError ? 'email-error' : undefined"
            @input="clearErrors"
          />
        </label>
        <p v-if="emailError" id="email-error" class="field-error" role="alert">{{ emailError }}</p>

        <button type="submit">
          {{ authStore.status === 'loading' ? 'Envoi...' : 'Recevoir les instructions' }}
        </button>
      </fieldset>
    </form>

    <RouterLink v-if="!successMessage" class="back-link" :to="{ name: 'login' }">
      Retour à la connexion
    </RouterLink>
  </main>
  <img class="auth-illustration" :src="forgotPasswordIllustration" alt="" aria-hidden="true" />
  </div>
</template>

<style scoped>
.auth-card { max-width: 34rem; margin: 0 auto; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.kicker { margin: 0 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1; }
.intro { margin: 0.9rem 0 0; color: #50634d; line-height: 1.6; }
form { margin-top: 1.75rem; }
fieldset { display: grid; gap: 1rem; margin: 0; padding: 0; border: 0; }
.field { display: grid; gap: 0.45rem; }
.field span { font-weight: 700; }
input { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
button { display: block; width: fit-content; max-width: 100%; margin: 0.25rem auto 0; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { margin-bottom: 1rem; padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
.success-message p { margin: 0; }
.success-message a, .back-link { display: inline-block; margin-top: 0.9rem; color: #2f4520; font-weight: 700; }
.back-link { margin-top: 1.5rem; }
</style>
