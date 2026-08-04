<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import OAuthAccountsSection from '@/components/OAuthAccountsSection.vue';
import { fetchNotificationPreferences, updateNotificationPreferences, type NotificationChannel, type NotificationPreference, type NotificationType } from '@/utils/notificationPreferences';

type TextField = 'name' | 'email' | 'currentPassword' | 'allergyDraft' | 'defaultServings';
type ErrorField = TextField | 'avatar' | 'diet' | 'allergies' | 'defaultServings';

const dietOptions = [
  { value: 'omnivore', label: 'Omnivore' },
  { value: 'vegetarian', label: 'Végétarien' },
  { value: 'vegan', label: 'Végétalien' },
  { value: 'pescatarian', label: 'Pescétarien' },
  { value: 'flexitarian', label: 'Flexitarien' },
  { value: 'halal', label: 'Halal' },
  { value: 'kosher', label: 'Casher' },
];

const authStore = useAuthStore();
const originalEmail = authStore.user?.email ?? '';
const form = reactive({
  name: authStore.user?.name ?? '',
  email: authStore.user?.email ?? '',
  avatar: null as File | null,
  currentPassword: '',
  diet: authStore.user?.diet ?? null,
  allergies: [...(authStore.user?.allergies ?? [])],
  defaultServings: authStore.user?.default_servings ?? 2,
});
const allergyDraft = ref('');
const clientErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '', diet: '', allergies: '', defaultServings: '', allergyDraft: '' });
const apiErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '', diet: '', allergies: '', defaultServings: '', allergyDraft: '' });
const touched = reactive<Record<ErrorField, boolean>>({ name: false, email: false, avatar: false, currentPassword: false, diet: false, allergies: false, defaultServings: false, allergyDraft: false });
const hasSubmitted = ref(false);
const globalError = ref('');
const successMessage = ref('');
const notificationPreferences = reactive<Record<NotificationType, NotificationChannel>>({
  recipe_comment: 'both',
  recipe_comment_reply: 'both',
  cookbook_message: 'both',
});
const notificationLoading = ref(true);
const notificationSaving = ref(false);
const notificationError = ref('');
const notificationSuccess = ref('');
const notificationTypes: { type: NotificationType; label: string }[] = [
  { type: 'recipe_comment', label: 'Commentaires sur mes recettes' },
  { type: 'recipe_comment_reply', label: 'Réponses à mes commentaires' },
  { type: 'cookbook_message', label: 'Messages de mes cookbooks' },
];
const notificationChannels: { value: NotificationChannel; label: string }[] = [
  { value: 'none', label: 'Aucune notification' },
  { value: 'web', label: 'Application web' },
  { value: 'mail', label: 'E-mail' },
  { value: 'both', label: 'Application web et e-mail' },
];
const formErrorSummary = ref<HTMLElement | null>(null);
const avatarPreview = ref<string | null>(authStore.user?.avatar_url ?? null);
let objectPreviewUrl: string | null = null;

function emailChanged(): boolean {
  return form.email.trim().toLowerCase() !== originalEmail;
}

function validateField(field: ErrorField): string {
  if (field === 'avatar') return '';
  if (field === 'allergyDraft') return allergyDraft.value.trim().length > 100 ? 'Une allergie ne peut pas dépasser 100 caractères.' : '';
  if (field === 'currentPassword') return emailChanged() && form.currentPassword === '' ? 'Ce champ est requis pour modifier l email.' : '';
  if (field === 'name') {
    if (form.name.trim() === '') return 'Le nom est requis.';
    if (form.name.trim().length < 2) return 'Le nom doit contenir au moins 2 caracteres.';
  }
  if (field === 'email') {
    if (form.email.trim() === '') return 'L adresse e-mail est requise.';
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) return 'Saisissez une adresse e-mail valide.';
  }
  if (field === 'allergies' && form.allergies.length > 50) return 'Vous pouvez renseigner au maximum 50 allergies.';
  if (field === 'defaultServings' && (!Number.isInteger(form.defaultServings) || form.defaultServings < 1 || form.defaultServings > 50)) return 'Indiquez un nombre de portions entre 1 et 50.';
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

function addAllergy(): void {
  const allergy = allergyDraft.value.trim();
  if (allergy === '') return;
  if (allergy.length > 100) { clientErrors.allergyDraft = 'Une allergie ne peut pas dépasser 100 caractères.'; return; }
  if (!form.allergies.includes(allergy)) form.allergies.push(allergy);
  allergyDraft.value = '';
  clientErrors.allergyDraft = '';
  apiErrors.allergies = '';
}

function removeAllergy(allergy: string): void { form.allergies = form.allergies.filter((item) => item !== allergy); }

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
  (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings', 'allergyDraft'] as ErrorField[]).forEach((field) => {
    touched[field] = true;
    validateAndStore(field);
  });
  return (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings', 'allergyDraft'] as ErrorField[]).every((field) => visibleError(field) === '');
}

async function focusFirstError(): Promise<void> {
  await nextTick();
  const field = (['name', 'email', 'avatar', 'diet', 'allergies', 'defaultServings', 'allergyDraft', 'currentPassword'] as ErrorField[]).find((item) => visibleError(item) !== '');
  if (field !== undefined) document.getElementById(`${field === 'allergyDraft' ? 'allergy' : field}-input`)?.focus();
  else formErrorSummary.value?.focus();
}

async function handleSubmit(): Promise<void> {
  hasSubmitted.value = true;
  globalError.value = '';
  successMessage.value = '';
  (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings'] as ErrorField[]).forEach((field) => { apiErrors[field] = ''; });
  if (!validateForm()) { await focusFirstError(); return; }

  const result = await authStore.updateProfile(form.name, form.email, form.avatar, form.currentPassword, originalEmail, form.diet, form.allergies, form.defaultServings);
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
  apiErrors.diet = result.fieldErrors.diet ?? '';
  apiErrors.allergies = result.fieldErrors.allergies ?? '';
  apiErrors.defaultServings = result.fieldErrors.default_servings ?? '';
  await focusFirstError();
}

onBeforeUnmount(revokeObjectPreview);

onMounted(async () => {
  if (authStore.user === null) return;
  const result = await fetchNotificationPreferences(authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    result.preferences.forEach((preference) => { notificationPreferences[preference.type] = preference.channel; });
  } else {
    notificationError.value = result.message;
  }
  notificationLoading.value = false;
});

async function saveNotificationPreferences(): Promise<void> {
  notificationSaving.value = true;
  notificationError.value = '';
  notificationSuccess.value = '';
  const preferences = notificationTypes.map(({ type }): NotificationPreference => ({ type, channel: notificationPreferences[type] }));
  const result = await updateNotificationPreferences(authStore.tokenType, authStore.accessToken, preferences);
  if (result.ok) {
    result.preferences.forEach((preference) => { notificationPreferences[preference.type] = preference.channel; });
    notificationSuccess.value = 'Vos préférences de notifications ont été enregistrées.';
  } else {
    notificationError.value = result.message;
  }
  notificationSaving.value = false;
}
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
          <input id="avatar-input" type="file" accept="image/jpeg,image/png,image/webp" :aria-invalid="visibleError('avatar') ? 'true' : 'false'" aria-describedby="avatar-help" @change="handleAvatarChange" />
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

        <section class="preferences-section" aria-labelledby="food-preferences-title">
          <h3 id="food-preferences-title">Préférences culinaires</h3>
          <p class="section-help">Ces préférences servent à personnaliser vos suggestions de recettes. Vous pourrez les modifier à tout moment.</p>

          <div class="field">
            <label for="diet-input">Régime alimentaire</label>
            <select id="diet-input" v-model="form.diet" name="diet" :aria-invalid="visibleError('diet') ? 'true' : 'false'" aria-describedby="diet-help diet-error" @change="apiErrors.diet = ''">
              <option :value="null">Aucun régime particulier</option>
              <option v-for="option in dietOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <small id="diet-help">Choisissez une valeur dans la liste pour garder des préférences cohérentes.</small>
            <p v-if="visibleError('diet')" id="diet-error" class="field-error" role="alert">{{ visibleError('diet') }}</p>
          </div>

          <div class="field">
            <label for="allergy-input">Allergies et ingrédients à éviter</label>
            <div class="tag-list" aria-live="polite">
              <span v-for="allergy in form.allergies" :key="allergy" class="tag">
                {{ allergy }}
                <button type="button" class="remove-tag" :aria-label="'Retirer ' + allergy" @click="removeAllergy(allergy)">×</button>
              </span>
              <span v-if="form.allergies.length === 0" class="empty-tags">Aucune allergie renseignée.</span>
            </div>
            <div class="allergy-entry">
              <input id="allergy-input" v-model="allergyDraft" name="allergy" maxlength="100" placeholder="Ex. arachides" :aria-invalid="clientErrors.allergyDraft || visibleError('allergies') ? 'true' : 'false'" aria-describedby="allergy-help allergy-error" @keydown.enter.prevent="addAllergy" @input="apiErrors.allergies = ''; clientErrors.allergyDraft = ''" />
              <button type="button" class="secondary-button" @click="addAllergy">Ajouter</button>
            </div>
            <small id="allergy-help">Ajoutez chaque allergie séparément. Appuyez sur Entrée ou sur Ajouter.</small>
            <p v-if="clientErrors.allergyDraft" id="allergy-error" class="field-error" role="alert">{{ clientErrors.allergyDraft }}</p>
            <p v-else-if="visibleError('allergies')" id="allergy-error" class="field-error" role="alert">{{ visibleError('allergies') }}</p>
          </div>

          <div class="field">
            <label for="defaultServings-input">Portions par défaut</label>
            <input id="defaultServings-input" v-model.number="form.defaultServings" name="default_servings" type="number" min="1" max="50" :aria-invalid="visibleError('defaultServings') ? 'true' : 'false'" aria-describedby="defaultServings-help defaultServings-error" @input="handleInput('defaultServings')" @blur="handleBlur('defaultServings')" />
            <small id="defaultServings-help">Nombre de personnes pour lequel vous cuisinez habituellement (de 1 à 50).</small>
            <p v-if="visibleError('defaultServings')" id="defaultServings-error" class="field-error" role="alert">{{ visibleError('defaultServings') }}</p>
          </div>
        </section>

        <section class="preferences-section" aria-labelledby="notification-preferences-title">
          <h3 id="notification-preferences-title">Préférences de notifications</h3>
          <p class="section-help">Choisissez comment vous souhaitez être informé pour chaque source de notification.</p>
          <p v-if="notificationLoading" role="status">Chargement des préférences de notifications...</p>
          <p v-if="notificationError" class="field-error" role="alert">{{ notificationError }}</p>
          <div v-for="item in notificationTypes" :key="item.type" class="notification-preference">
            <label :for="`${item.type}-notification-input`">{{ item.label }}</label>
            <select :id="`${item.type}-notification-input`" v-model="notificationPreferences[item.type]" :disabled="notificationLoading || notificationSaving">
              <option v-for="channel in notificationChannels" :key="channel.value" :value="channel.value">{{ channel.label }}</option>
            </select>
          </div>
          <button type="button" class="secondary-button" :disabled="notificationLoading || notificationSaving" @click="saveNotificationPreferences">
            {{ notificationSaving ? 'Enregistrement...' : 'Enregistrer les notifications' }}
          </button>
          <p v-if="notificationSuccess" role="status" class="success-message">{{ notificationSuccess }}</p>
        </section>

        <button type="submit">{{ authStore.status === 'loading' ? 'Enregistrement...' : 'Enregistrer le profil' }}</button>
      </fieldset>
    </form>

    <OAuthAccountsSection />
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
.preferences-section { display: grid; gap: 1.25rem; margin-top: 0.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
h3 { margin: 0; font-size: 1.35rem; }
.section-help { margin: -0.75rem 0 0; color: #50634d; line-height: 1.5; }
.notification-preference { display: grid; gap: 0.45rem; padding: 0.9rem 0; border-top: 1px solid rgba(86, 112, 79, 0.12); }
.notification-preference select { max-width: 30rem; }
label { font-weight: 700; }
input, select { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible, select:focus-visible, button:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'], select[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
small { color: #50634d; }
.tag-list { display: flex; flex-wrap: wrap; gap: 0.5rem; min-height: 2rem; align-items: center; }
.tag { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.55rem 0.35rem 0.75rem; border-radius: 999px; background: #e6efdc; color: #2f4520; font-size: 0.92rem; }
.remove-tag { margin: 0; padding: 0; border: 0; background: transparent; color: #2f4520; font-size: 1.2rem; line-height: 1; cursor: pointer; }
.allergy-entry { display: flex; gap: 0.6rem; }
.allergy-entry input { flex: 1; }
.secondary-button { margin: 0; padding: 0.75rem 1rem; background: #e6efdc; color: #2f4520; }
.empty-tags { color: #50634d; font-size: 0.92rem; }
.avatar-preview { width: 7rem; height: 7rem; border: 1px solid rgba(86,112,79,.2); border-radius: 50%; object-fit: cover; }
.avatar-figure { display: grid; justify-items: start; gap: .4rem; margin: 0; }
.avatar-figure figcaption { color: #50634d; font-size: .9rem; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
button { margin-top: 0.25rem; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
</style>
