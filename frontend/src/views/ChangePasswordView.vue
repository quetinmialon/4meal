<script setup lang="ts">
import { nextTick, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import OAuthAccountsSection from '@/components/OAuthAccountsSection.vue';

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
const twoFactorEnabled = ref(authStore.user?.two_factor_enabled ?? false);
const twoFactorPassword = ref('');
const twoFactorError = ref('');
const twoFactorSuccess = ref('');
const twoFactorLoading = ref(false);

async function toggleTwoFactor(): Promise<void> {
  twoFactorLoading.value = true;
  twoFactorError.value = '';
  twoFactorSuccess.value = '';
  const result = await authStore.setTwoFactorEnabled(!twoFactorEnabled.value, twoFactorPassword.value);
  twoFactorLoading.value = false;
  if (!result.ok) {
    twoFactorError.value = result.message;
    return;
  }
  twoFactorEnabled.value = result.enabled;
  twoFactorPassword.value = '';
  twoFactorSuccess.value = result.enabled ? 'La vérification en deux étapes est activée.' : 'La vérification en deux étapes est désactivée.';
}

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
    <p class="kicker">Mon compte</p>
    <h1>Sécurité du compte</h1>
    <p class="intro">Consultez l’état de vos protections et gérez les actions sensibles de votre compte.</p>

    <section class="security-status" aria-labelledby="security-status-title">
      <h2 id="security-status-title">État des protections</h2>
      <div class="security-status-grid">
        <div class="security-status-item">
          <div><strong>Adresse email</strong><span>{{ authStore.user?.email }}</span></div>
          <span v-if="authStore.user?.email_verified === true" class="status-badge enabled">Vérifiée</span>
          <span v-else class="status-badge pending">À vérifier</span>
          <RouterLink v-if="authStore.user?.email_verified !== true" class="status-link" :to="{ name: 'email-verification-pending' }">Vérifier l’adresse</RouterLink>
        </div>
        <div class="security-status-item">
          <div><strong>Authentification à deux facteurs</strong><span>Code envoyé par email lors des connexions.</span></div>
          <span class="status-badge" :class="authStore.user?.two_factor_enabled === true ? 'enabled' : 'disabled'">{{ authStore.user?.two_factor_enabled === true ? 'Activée' : 'Désactivée' }}</span>
          <a class="status-link" href="#two-factor-security">Gérer la 2FA</a>
        </div>
      </div>
    </section>

    <section class="sensitive-action" aria-labelledby="password-title">
      <p class="section-kicker">Action sensible</p>
      <h2 id="password-title">Modifier le mot de passe</h2>
      <p class="section-help">Utilisez votre mot de passe actuel pour en définir un nouveau d’au moins 8 caractères.</p>

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
    </section>

    <section id="two-factor-security" class="security-control" aria-labelledby="two-factor-title">
      <p class="section-kicker">Protection du compte</p>
      <h2 id="two-factor-title">Authentification à deux facteurs</h2>
      <p class="section-help">Un code temporaire est demandé par email lors des nouvelles connexions.</p>
      <p v-if="twoFactorError" class="error-summary" role="alert">{{ twoFactorError }}</p>
      <p v-if="twoFactorSuccess" class="success-message" role="status">{{ twoFactorSuccess }}</p>
      <div v-if="twoFactorEnabled" class="two-factor-enabled">
        <strong>Protection active</strong>
        <label for="two-factor-password-input">Mot de passe actuel pour désactiver</label>
        <input id="two-factor-password-input" v-model="twoFactorPassword" type="password" autocomplete="current-password">
        <button type="button" class="danger-button" :disabled="twoFactorLoading || twoFactorPassword === ''" @click="toggleTwoFactor">
          {{ twoFactorLoading ? 'Modification…' : 'Désactiver la 2FA' }}
        </button>
      </div>
      <button v-else type="button" class="secondary-button" :disabled="twoFactorLoading" @click="toggleTwoFactor">
        {{ twoFactorLoading ? 'Activation…' : 'Activer la 2FA par email' }}
      </button>
    </section>

    <section class="security-control" aria-labelledby="oauth-title">
      <p class="section-kicker">Connexions externes</p>
      <h2 id="oauth-title">Comptes OAuth</h2>
      <OAuthAccountsSection />
    </section>
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

h1 { margin: 0 0 .75rem; font-size: clamp(2rem, 4vw, 3rem); line-height: 1; }
h2 { margin: 0 0 0.75rem; font-size: 1.45rem; line-height: 1.2; }
.intro { margin: 0; color: #50634d; line-height: 1.6; }
.security-status { margin-top: 1.75rem; padding: 1.25rem; border: 1px solid rgba(86, 112, 79, .18); border-radius: 1rem; background: #f7fbf3; }
.security-status h2 { margin-bottom: 1rem; }
.security-status-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.security-status-item { display: grid; gap: .65rem; padding: .9rem; border: 1px solid #d8e1d2; border-radius: .7rem; background: #fffdf8; }
.security-status-item > div { display: grid; gap: .25rem; }.security-status-item > div span { color: #50634d; font-size: .9rem; line-height: 1.4; overflow-wrap: anywhere; }
.status-badge { display: inline-flex; width: fit-content; max-height: 20px; align-items: center; padding: .2rem .55rem; border-radius: 999px; font-size: .8rem; font-weight: 800; line-height: 1; }.status-badge.enabled { background: #e6efdc; color: #2f4520; }.status-badge.pending, .status-badge.disabled { background: #fff1dc; color: #704414; }
.status-link { width: fit-content; color: #395330; font-weight: 700; }
.sensitive-action { margin-top: 1.5rem; padding: 1.25rem; border: 1px solid #e2b3ad; border-radius: 1rem; background: #fffaf8; }.sensitive-action .section-kicker { margin: 0 0 .25rem; color: #8f1e1e; }.section-help { margin: 0; color: #50634d; line-height: 1.5; }
.security-control { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, .18); }.security-control .section-kicker { margin: 0 0 .25rem; color: #6b7b57; font-size: .75rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }.security-control h2 { margin-bottom: .5rem; }.security-control .section-help { margin-bottom: 1rem; }.two-factor-enabled { display: grid; gap: .8rem; }.secondary-button, .danger-button { width: fit-content; margin: 0; padding: .75rem 1rem; border: 0; border-radius: 999px; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }.secondary-button { background: #e6efdc; color: #2f4520; }.danger-button { background: #8f1e1e; }
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
@media (max-width: 680px) { .password-card { padding: 1rem; }.security-status-grid { grid-template-columns: 1fr; }.security-status, .sensitive-action { padding: 1rem; } }
</style>
