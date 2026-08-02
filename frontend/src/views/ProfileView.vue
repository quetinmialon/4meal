<script setup lang="ts">
import { nextTick, onBeforeUnmount, reactive, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';

type TextField = 'name' | 'email' | 'currentPassword';
type ErrorField = TextField | 'avatar';

const authStore = useAuthStore();
const originalEmail = authStore.user?.email ?? '';
const form = reactive({
  name: authStore.user?.name ?? '',
  email: authStore.user?.email ?? '',
  avatar: null as File | null,
  currentPassword: '',
});
const clientErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '' });
const apiErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '' });
const touched = reactive<Record<ErrorField, boolean>>({ name: false, email: false, avatar: false, currentPassword: false });
const hasSubmitted = ref(false);
const globalError = ref('');
const successMessage = ref('');
const formErrorSummary = ref<HTMLElement | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarPreview = ref<string | null>(authStore.user?.avatar_url ?? null);
let objectPreviewUrl: string | null = null;

function emailChanged(): boolean {
  return form.email.trim().toLowerCase() !== originalEmail;
}

function validateField(field: ErrorField): string {
  if (field === 'avatar') return '';
  if (field === 'currentPassword') return emailChanged() && form.currentPassword === '' ? 'Ce champ est requis pour modifier l email.' : '';
  if (field === 'name') {
    if (form.name.trim() === '') return 'Le nom est requis.';
    if (form.name.trim().length < 2) return 'Le nom doit contenir au moins 2 caracteres.';
  }
  if (field === 'email') {
    if (form.email.trim() === '') return 'L adresse e-mail est requise.';
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) return 'Saisissez une adresse e-mail valide.';
  }
  return '';
}

function visibleError(field: ErrorField): string { return apiErrors[field] || clientErrors[field]; }

function validateAndStore(field: ErrorField): void { clientErrors[field] = validateField(field); }

function handleInput(field: TextField): void {
  apiErrors[field] = '';
  globalError.value = '';
  successMessage.value = '';
  if (touched[field] || hasSubmitted.value) validateAndStore(field);
  if (field === 'email' && (touched.currentPassword || hasSubmitted.value)) validateAndStore('currentPassword');
}

function handleBlur(field: TextField): void {
  touched[field] = true;
  validateAndStore(field);
}

function revokeObjectPreview(): void {
  if (objectPreviewUrl !== null) URL.revokeObjectURL(objectPreviewUrl);
  objectPreviewUrl = null;
}

function handleAvatarChange(event: Event): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] ?? null;
  apiErrors.avatar = '';
  globalError.value = '';
  successMessage.value = '';
  if (file === null) return;

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    apiErrors.avatar = 'Le fichier doit etre au format JPEG, PNG ou WebP.';
    input.value = '';
    form.avatar = null;
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    apiErrors.avatar = 'L image ne doit pas depasser 5 Mo.';
    input.value = '';
    form.avatar = null;
    return;
  }

  revokeObjectPreview();
  form.avatar = file;
  objectPreviewUrl = URL.createObjectURL(file);
  avatarPreview.value = objectPreviewUrl;
}

function validateForm(): boolean {
  (['name', 'email', 'avatar', 'currentPassword'] as ErrorField[]).forEach((field) => {
    touched[field] = true;
    validateAndStore(field);
  });
  return (['name', 'email', 'avatar', 'currentPassword'] as ErrorField[]).every((field) => visibleError(field) === '');
}

async function focusFirstError(): Promise<void> {
  await nextTick();
  const field = (['name', 'email', 'avatar', 'currentPassword'] as ErrorField[]).find((item) => visibleError(item) !== '');
  if (field !== undefined) document.getElementById(`${field}-input`)?.focus();
  else formErrorSummary.value?.focus();
}

async function handleSubmit(): Promise<void> {
  hasSubmitted.value = true;
  globalError.value = '';
  successMessage.value = '';
  (['name', 'email', 'avatar', 'currentPassword'] as ErrorField[]).forEach((field) => { apiErrors[field] = ''; });
  if (!validateForm()) { await focusFirstError(); return; }

  const result = await authStore.updateProfile(form.name, form.email, form.avatar, form.currentPassword, originalEmail);
  if (result.ok) {
    successMessage.value = 'Votre profil a bien ete modifie.';
    form.currentPassword = '';
    return;
  }

  globalError.value = result.message;
  apiErrors.name = result.fieldErrors.name ?? '';
  apiErrors.email = result.fieldErrors.email ?? '';
  apiErrors.avatar = result.fieldErrors.avatar_path ?? '';
  apiErrors.currentPassword = result.fieldErrors.current_password ?? '';
  await focusFirstError();
}

onBeforeUnmount(revokeObjectPreview);
</script>

<template>
  <main class="profile-card">
    <p class="kicker">Mon compte</p>
    <h2>Modifier mon profil</h2>
    <p class="intro">Mettez a jour les informations visibles sur votre compte.</p>
    <p v-if="successMessage" class="success-message" role="status" aria-live="polite">{{ successMessage }}</p>

    <form class="profile-form" novalidate @submit.prevent="handleSubmit">
      <fieldset :disabled="authStore.status === 'loading'">
        <div v-if="globalError" ref="formErrorSummary" class="error-summary" role="alert" aria-live="assertive" tabindex="-1">{{ globalError }}</div>

        <div class="field">
          <label for="name-input">Nom</label>
          <input id="name-input" v-model="form.name" name="name" autocomplete="name" :aria-invalid="visibleError('name') ? 'true' : 'false'" @blur="handleBlur('name')" @input="handleInput('name')" />
          <p v-if="visibleError('name')" id="name-error" class="field-error" role="alert">{{ visibleError('name') }}</p>
        </div>

        <div class="field">
          <label for="email-input">Adresse e-mail</label>
          <input id="email-input" v-model="form.email" name="email" type="email" autocomplete="email" :aria-invalid="visibleError('email') ? 'true' : 'false'" @blur="handleBlur('email')" @input="handleInput('email')" />
          <p v-if="visibleError('email')" id="email-error" class="field-error" role="alert">{{ visibleError('email') }}</p>
        </div>

        <div class="field">
          <label for="avatar-input">Photo de profil</label>
          <input id="avatar-input" ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" :aria-invalid="visibleError('avatar') ? 'true' : 'false'" aria-describedby="avatar-help" @change="handleAvatarChange" />
          <small id="avatar-help">JPEG, PNG ou WebP, 5 Mo maximum.</small>
          <figure v-if="avatarPreview" class="avatar-figure">
            <img class="avatar-preview" :src="avatarPreview" alt="Photo actuelle du profil" />
            <figcaption>Photo actuelle</figcaption>
          </figure>
          <p v-if="visibleError('avatar')" id="avatar-error" class="field-error" role="alert">{{ visibleError('avatar') }}</p>
        </div>

        <div class="field">
          <label for="currentPassword-input">Mot de passe actuel</label>
          <input id="currentPassword-input" v-model="form.currentPassword" name="currentPassword" type="password" autocomplete="current-password" :aria-invalid="visibleError('currentPassword') ? 'true' : 'false'" @blur="handleBlur('currentPassword')" @input="handleInput('currentPassword')" />
          <small>Requis uniquement si vous modifiez votre adresse e-mail.</small>
          <p v-if="visibleError('currentPassword')" id="currentPassword-error" class="field-error" role="alert">{{ visibleError('currentPassword') }}</p>
        </div>

        <button type="submit">{{ authStore.status === 'loading' ? 'Enregistrement...' : 'Enregistrer le profil' }}</button>
      </fieldset>
    </form>
  </main>
</template>

<style scoped>
.profile-card { margin: 0 auto; max-width: 42rem; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.kicker { margin: 0 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h2 { margin: 0 0 0.75rem; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1; }
.intro { margin: 0; color: #50634d; line-height: 1.6; }
.profile-form { margin-top: 1.75rem; }
fieldset { display: grid; gap: 1.25rem; margin: 0; padding: 0; border: 0; }
.field { display: grid; gap: 0.45rem; }
label { font-weight: 700; }
input { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
small { color: #50634d; }
.avatar-preview { width: 7rem; height: 7rem; border: 1px solid rgba(86,112,79,.2); border-radius: 50%; object-fit: cover; }
.avatar-figure { display: grid; justify-items: start; gap: .4rem; margin: 0; }
.avatar-figure figcaption { color: #50634d; font-size: .9rem; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
button { margin-top: 0.25rem; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
</style>
