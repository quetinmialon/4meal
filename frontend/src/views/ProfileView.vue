<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import OAuthAccountsSection from '@/components/OAuthAccountsSection.vue';
import { fetchNotificationPreferences, updateNotificationPreferences, type NotificationChannel, type NotificationPreference, type NotificationType } from '@/utils/notificationPreferences';
import { applyThemePreference, initialThemePreference, type ThemePreference } from '@/utils/theme';

type TextField = 'name' | 'email' | 'currentPassword' | 'allergyDraft' | 'defaultServings';
type ErrorField = TextField | 'avatar' | 'diet' | 'allergies' | 'defaultServings' | 'theme';

const dietOptions = [
  { value: 'omnivore', label: 'Omnivore' },
  { value: 'vegetarian', label: 'Végétarien' },
  { value: 'vegan', label: 'Végétalien' },
  { value: 'pescatarian', label: 'Pescétarien' },
  { value: 'flexitarian', label: 'Flexitarien' },
  { value: 'halal', label: 'Halal' },
  { value: 'kosher', label: 'Casher' },
];

const themeOptions: { value: ThemePreference; label: string }[] = [
  { value: 'light', label: 'Thème clair' },
  { value: 'dark', label: 'Thème sombre' },
  { value: 'system', label: 'Selon les réglages du système' },
];

const authStore = useAuthStore();
const route = useRoute();
const settingsSection = computed<'all' | 'profile' | 'food' | 'usage'>(() => {
  const path = route?.path ?? window.location.pathname;
  if (path.endsWith('/preferences-alimentaires')) return 'food';
  if (path.endsWith('/preferences-utilisation')) return 'usage';
  return path === '/profil' ? 'profile' : 'all';
});
const settingsPageTitle = computed(() => settingsSection.value === 'food' ? 'Préférences alimentaires' : settingsSection.value === 'usage' ? 'Préférences d’utilisation' : 'Profil');
const settingsPageHeading = computed(() => settingsSection.value === 'food' ? 'Vos habitudes alimentaires' : settingsSection.value === 'usage' ? 'Vos préférences d’utilisation' : 'Informations générales');
const settingsPageIntro = computed(() => settingsSection.value === 'food' ? 'Gérez votre régime, vos allergies et vos portions par défaut.' : settingsSection.value === 'usage' ? 'Choisissez l’apparence de l’application et la manière de recevoir vos notifications.' : 'Gérez les informations principales de votre compte.');
const originalEmail = authStore.user?.email ?? '';
const form = reactive({
  name: authStore.user?.name ?? '',
  email: authStore.user?.email ?? '',
  avatar: null as File | null,
  currentPassword: '',
  diet: authStore.user?.diet ?? null,
  allergies: [...(authStore.user?.allergies ?? [])],
  defaultServings: authStore.user?.default_servings ?? 2,
  theme: initialThemePreference(authStore.user?.theme),
});
const allergyDraft = ref('');
const clientErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '', diet: '', allergies: '', defaultServings: '', theme: '', allergyDraft: '' });
const apiErrors = reactive<Record<ErrorField, string>>({ name: '', email: '', avatar: '', currentPassword: '', diet: '', allergies: '', defaultServings: '', theme: '', allergyDraft: '' });
const touched = reactive<Record<ErrorField, boolean>>({ name: false, email: false, avatar: false, currentPassword: false, diet: false, allergies: false, defaultServings: false, theme: false, allergyDraft: false });
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
const twoFactorEnabled = ref(authStore.user?.two_factor_enabled ?? false);
const twoFactorPassword = ref('');
const twoFactorError = ref('');
const twoFactorSuccess = ref('');
const twoFactorLoading = ref(false);
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
const notificationGroups = [
  { label: 'Recettes', types: notificationTypes.filter(({ type }) => type !== 'cookbook_message') },
  { label: 'Cookbooks', types: notificationTypes.filter(({ type }) => type === 'cookbook_message') },
];

function channelIncludes(type: NotificationType, channel: 'web' | 'mail'): boolean {
  const current = notificationPreferences[type];
  return current === channel || current === 'both';
}

function updateNotificationChannel(type: NotificationType, channel: 'web' | 'mail', enabled: boolean): void {
  const webEnabled = channel === 'web' ? enabled : channelIncludes(type, 'web');
  const mailEnabled = channel === 'mail' ? enabled : channelIncludes(type, 'mail');
  notificationPreferences[type] = webEnabled ? (mailEnabled ? 'both' : 'web') : (mailEnabled ? 'mail' : 'none');
}
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

function selectTheme(theme: ThemePreference): void {
  form.theme = theme;
  applyThemePreference(theme);
  apiErrors.theme = '';
  successMessage.value = '';
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
  (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings', 'theme', 'allergyDraft'] as ErrorField[]).forEach((field) => {
    touched[field] = true;
    validateAndStore(field);
  });
  return (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings', 'theme', 'allergyDraft'] as ErrorField[]).every((field) => visibleError(field) === '');
}

async function focusFirstError(): Promise<void> {
  await nextTick();
  const field = (['name', 'email', 'avatar', 'diet', 'allergies', 'defaultServings', 'theme', 'allergyDraft', 'currentPassword'] as ErrorField[]).find((item) => visibleError(item) !== '');
  if (field !== undefined) document.getElementById(`${field === 'allergyDraft' ? 'allergy' : field}-input`)?.focus();
  else formErrorSummary.value?.focus();
}

async function handleSubmit(): Promise<void> {
  hasSubmitted.value = true;
  globalError.value = '';
  successMessage.value = '';
  (['name', 'email', 'avatar', 'currentPassword', 'diet', 'allergies', 'defaultServings', 'theme'] as ErrorField[]).forEach((field) => { apiErrors[field] = ''; });
  if (!validateForm()) { await focusFirstError(); return; }

  const result = await authStore.updateProfile(form.name, form.email, form.avatar, form.currentPassword, originalEmail, form.diet, form.allergies, form.defaultServings, form.theme);
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
  apiErrors.theme = result.fieldErrors.theme ?? '';
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
  twoFactorSuccess.value = result.enabled ? 'La verification en deux etapes est activee.' : 'La verification en deux etapes est desactivee.';
}
</script>

<template>
  <main class="profile-card">
    <p class="kicker">Mon compte</p>
    <h1>{{ settingsPageTitle }}</h1>
    <h2 id="profile-form-title">{{ settingsPageHeading }}</h2>
    <p class="intro">{{ settingsPageIntro }}</p>
    <p v-if="successMessage" class="success-message" role="status" aria-live="polite">{{ successMessage }}</p>

    <form id="profile-settings" class="profile-form" aria-labelledby="profile-form-title" novalidate @submit.prevent="handleSubmit">
      <fieldset :disabled="authStore.status === 'loading'">
        <div v-if="globalError" ref="formErrorSummary" class="error-summary" role="alert" aria-live="assertive" tabindex="-1">{{ globalError }}</div>

        <section v-if="settingsSection === 'all' || settingsSection === 'profile'" class="profile-general" aria-labelledby="profile-form-title">
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
        <a class="password-link" href="/securite">Changer le mot de passe</a>
        </section>

        <section v-if="settingsSection === 'all' || settingsSection === 'usage'" id="theme-preferences" class="preferences-section" aria-labelledby="theme-preferences-title">
          <h3 id="theme-preferences-title">Thème</h3>
          <p class="section-help">Choisissez l’apparence de l’application. Le mode système suit automatiquement les réglages de votre appareil.</p>
          <div class="field">
            <label for="theme-input">Apparence</label>
            <div class="theme-options" role="radiogroup" aria-label="Choix de l’apparence" aria-describedby="theme-help theme-error">
              <label v-for="option in themeOptions" :key="option.value" class="theme-option" :class="{ selected: form.theme === option.value }" :for="`theme-${option.value}-input`">
                <input :id="`theme-${option.value}-input`" v-model="form.theme" type="radio" name="theme" :value="option.value" :aria-invalid="visibleError('theme') ? 'true' : 'false'" @change="selectTheme(option.value)">
                <span>{{ option.label }}</span>
              </label>
            </div>
            <select id="theme-input" v-model="form.theme" class="visually-hidden" aria-hidden="true" tabindex="-1" @change="selectTheme(form.theme)">
              <option v-for="option in themeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <small id="theme-help">Le choix est conservé sur cet appareil et les modes clair/sombre sont synchronisés avec votre profil.</small>
            <p v-if="visibleError('theme')" id="theme-error" class="field-error" role="alert">{{ visibleError('theme') }}</p>
          </div>
        </section>

        <section v-if="settingsSection === 'all' || settingsSection === 'food'" id="food-preferences" class="preferences-section food-preferences-section" aria-labelledby="food-preferences-title">
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
            <div class="tag-list" role="list" aria-label="Allergies enregistrées" aria-live="polite">
              <span v-for="allergy in form.allergies" :key="allergy" class="tag" role="listitem">
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
            <input id="defaultServings-input" v-model.number="form.defaultServings" name="default_servings" type="number" inputmode="numeric" min="1" max="50" :aria-invalid="visibleError('defaultServings') ? 'true' : 'false'" aria-describedby="defaultServings-help defaultServings-error" @input="handleInput('defaultServings')" @blur="handleBlur('defaultServings')" />
            <small id="defaultServings-help">Nombre de personnes pour lequel vous cuisinez habituellement (de 1 à 50).</small>
            <p v-if="visibleError('defaultServings')" id="defaultServings-error" class="field-error" role="alert">{{ visibleError('defaultServings') }}</p>
          </div>
        </section>

        <section v-if="settingsSection === 'all' || settingsSection === 'usage'" id="notification-preferences" class="preferences-section" aria-labelledby="notification-preferences-title">
          <h3 id="notification-preferences-title">Préférences de notifications</h3>
          <p class="section-help">Choisissez comment vous souhaitez être informé pour chaque source de notification.</p>
          <p v-if="notificationLoading" role="status">Chargement des préférences de notifications...</p>
          <p v-if="notificationError" class="field-error" role="alert">{{ notificationError }}</p>
          <fieldset v-for="group in notificationGroups" :key="group.label" class="notification-group">
            <legend>{{ group.label }}</legend>
            <div v-for="item in group.types" :key="item.type" class="notification-preference">
              <div class="notification-preference-heading">
                <strong>{{ item.label }}</strong>
                <span>Choisissez où recevoir cette notification.</span>
              </div>
              <div class="notification-switches">
                <label :for="`${item.type}-web-input`"><input :id="`${item.type}-web-input`" type="checkbox" :checked="channelIncludes(item.type, 'web')" :disabled="notificationLoading || notificationSaving" @change="updateNotificationChannel(item.type, 'web', ($event.target as HTMLInputElement).checked)"> Application web</label>
                <label :for="`${item.type}-mail-input`"><input :id="`${item.type}-mail-input`" type="checkbox" :checked="channelIncludes(item.type, 'mail')" :disabled="notificationLoading || notificationSaving" @change="updateNotificationChannel(item.type, 'mail', ($event.target as HTMLInputElement).checked)"> E-mail</label>
              </div>
              <label class="visually-hidden" :for="`${item.type}-notification-input`">Canal de notification {{ item.label }}</label>
              <select :id="`${item.type}-notification-input`" v-model="notificationPreferences[item.type]" class="visually-hidden" aria-hidden="true" tabindex="-1" :disabled="notificationLoading || notificationSaving">
                <option v-for="channel in notificationChannels" :key="channel.value" :value="channel.value">{{ channel.label }}</option>
              </select>
            </div>
          </fieldset>
          <button type="button" class="secondary-button" :disabled="notificationLoading || notificationSaving" @click="saveNotificationPreferences">
            {{ notificationSaving ? 'Enregistrement...' : 'Enregistrer les notifications' }}
          </button>
          <p v-if="notificationSuccess" role="status" class="success-message">{{ notificationSuccess }}</p>
        </section>

        <button type="submit">{{ authStore.status === 'loading' ? 'Enregistrement...' : 'Enregistrer le profil' }}</button>
      </fieldset>
    </form>

    <section v-if="settingsSection === 'all'" id="security-preferences" class="preferences-section" aria-labelledby="two-factor-title">
      <h3 id="two-factor-title">Verification en deux etapes</h3>
      <p class="section-help">Recevez un code temporaire par e-mail a chaque nouvelle connexion.</p>
      <p v-if="twoFactorError" class="error-summary" role="alert">{{ twoFactorError }}</p>
      <p v-if="twoFactorSuccess" class="success-message" role="status">{{ twoFactorSuccess }}</p>
      <div v-if="twoFactorEnabled" class="two-factor-enabled">
        <strong>Protection active</strong>
        <label class="field" for="two-factor-password-input">Mot de passe actuel pour desactiver</label>
        <input id="two-factor-password-input" v-model="twoFactorPassword" type="password" autocomplete="current-password" />
        <button type="button" class="danger-button" :disabled="twoFactorLoading || twoFactorPassword === ''" @click="toggleTwoFactor">
          {{ twoFactorLoading ? 'Modification...' : 'Desactiver la 2FA' }}
        </button>
      </div>
      <button v-else type="button" class="secondary-button" :disabled="twoFactorLoading" @click="toggleTwoFactor">
        {{ twoFactorLoading ? 'Activation...' : 'Activer la 2FA par e-mail' }}
      </button>
    </section>

    <section v-if="settingsSection === 'all'" id="connected-accounts">
      <OAuthAccountsSection />
    </section>
  </main>
</template>

<style scoped>
.profile-card { width: 100%; max-width: 76rem; margin: 0 auto; padding: 2rem; box-sizing: border-box; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.kicker { margin: 0 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
h1 { margin: 0 0 0.75rem; font-size: clamp(2rem, 4vw, 3rem); line-height: 1; }
.profile-card > h2 { margin: 0 0 0.75rem; font-size: 1.45rem; line-height: 1.2; }
.settings-content h2 { margin: 0 0 0.75rem; font-size: 1.45rem; line-height: 1.2; }
.intro { margin: 0; color: #50634d; line-height: 1.6; }
.settings-shell { display: grid; grid-template-columns: 15rem minmax(0, 1fr); gap: 2rem; margin-top: 2rem; align-items: start; }
.settings-nav { position: sticky; top: 1rem; display: grid; gap: .25rem; padding: .75rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1rem; background: #f7fbf3; }
.settings-nav-title { margin: .35rem .65rem .55rem; color: #6b7b57; font-size: .75rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.settings-nav a { padding: .7rem .75rem; border-radius: .65rem; color: #395330; font-weight: 700; line-height: 1.3; text-decoration: none; }
.settings-nav a:hover, .settings-nav a[aria-current='page'] { background: #e6efdc; }
.settings-nav a:focus-visible { background: #e6efdc; outline: 3px solid rgba(116, 144, 88, .45); outline-offset: 2px; }
.settings-content { min-width: 0; }
.profile-form { margin-top: 1.75rem; }
fieldset { display: grid; gap: 1.25rem; margin: 0; padding: 0; border: 0; }
.field { display: grid; gap: 0.45rem; }
.preferences-section { display: grid; gap: 1.25rem; margin-top: 0.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.food-preferences-section { scroll-margin-top: 1rem; }
h3 { margin: 0; font-size: 1.35rem; }
.section-help { margin: -0.75rem 0 0; color: #50634d; line-height: 1.5; }
.notification-preference { display: grid; gap: 0.45rem; padding: 0.9rem 0; border-top: 1px solid rgba(86, 112, 79, 0.12); }
.notification-group { display: grid; gap: .7rem; margin: 0; padding: .9rem; border: 1px solid rgba(86, 112, 79, .16); border-radius: .8rem; }.notification-group legend { padding: 0 .35rem; color: #395330; font-weight: 800; }.notification-preference-heading { display: grid; gap: .2rem; }.notification-preference-heading span { color: #50634d; font-size: .9rem; line-height: 1.4; }.notification-switches { display: flex; flex-wrap: wrap; gap: .6rem; }.notification-switches label { display: inline-flex; align-items: center; gap: .45rem; padding: .55rem .7rem; border: 1px solid #b9c5af; border-radius: 999px; background: #fffdf8; color: #243127; cursor: pointer; }.notification-switches input { width: 1.1rem; height: 1.1rem; padding: 0; accent-color: #395330; }.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
label { font-weight: 700; }
input, select { width: 100%; padding: 0.9rem 1rem; border: 1px solid #b4bead; border-radius: 0.95rem; background: #fffdfa; color: #243127; font: inherit; }
input:focus-visible, select:focus-visible, button:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 2px; }
input[aria-invalid='true'], select[aria-invalid='true'] { border-color: #b64242; background: #fff8f6; }
small { color: #50634d; }
.tag-list { display: flex; flex-wrap: wrap; gap: 0.5rem; min-height: 3.25rem; align-items: center; padding: .7rem; border: 1px solid #d8e1d2; border-radius: .8rem; background: #f7fbf3; }
.tag { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.55rem 0.35rem 0.75rem; border-radius: 999px; background: #e6efdc; color: #2f4520; font-size: 0.92rem; }
.remove-tag { margin: 0; padding: 0; border: 0; background: transparent; color: #2f4520; font-size: 1.2rem; line-height: 1; cursor: pointer; }
.allergy-entry { display: flex; gap: 0.6rem; }
.allergy-entry input { flex: 1; }
.secondary-button { margin: 0; padding: 0.75rem 1rem; background: #e6efdc; color: #2f4520; }
.empty-tags { color: #50634d; font-size: 0.92rem; }
.food-preferences-section .field > label { font-size: 1rem; }
.theme-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; }.theme-option { display: flex; align-items: flex-start; gap: .55rem; min-height: 100%; padding: .75rem; border: 1px solid #b9c5af; border-radius: .7rem; background: #fffdf8; color: #243127; cursor: pointer; line-height: 1.35; }.theme-option.selected { border-color: #395330; background: #edf4e6; box-shadow: 0 0 0 2px rgba(57, 83, 48, .12); }.theme-option input { flex: 0 0 auto; width: 1.1rem; height: 1.1rem; margin: .1rem 0 0; padding: 0; accent-color: #395330; }
.food-preferences-section .field > small { max-width: 42rem; }
.avatar-preview { width: 7rem; height: 7rem; border: 1px solid rgba(86,112,79,.2); border-radius: 50%; object-fit: cover; }
.avatar-figure { display: grid; justify-items: start; gap: .4rem; margin: 0; }
.avatar-figure figcaption { color: #50634d; font-size: .9rem; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; line-height: 1.5; }
.error-summary { padding: 0.95rem 1rem; border: 1px solid rgba(171, 44, 44, 0.24); border-radius: 1rem; background: #fff4f2; }
button { margin-top: 0.25rem; padding: 0.95rem 1.3rem; border: 0; border-radius: 999px; background: #2f4520; color: #fffdf9; font: inherit; font-weight: 700; cursor: pointer; }
button:disabled { cursor: wait; opacity: 0.8; }
.success-message { margin-top: 1.5rem; padding: 1rem; border: 1px solid #bdd0af; border-radius: 1rem; background: #edf4e6; color: #2f4520; }
.two-factor-enabled { display: grid; gap: .8rem; }
.two-factor-enabled .field { margin: 0; }
.secondary-button, .danger-button { width: fit-content; margin-top: 0; padding: .75rem 1rem; }
.secondary-button { background: #e6efdc; color: #2f4520; }
.danger-button { background: #fff4f2; color: #8f1e1e; }
@media (max-width: 760px) {
  .profile-card { padding: 1rem; }
  .settings-shell { display: block; margin-top: 1.5rem; }
  .settings-nav { position: static; display: flex; gap: .35rem; margin: 0 -0.25rem 1.5rem; padding: .5rem; overflow-x: auto; }
  .settings-nav-title { display: none; }
  .settings-nav a { flex: 0 0 auto; padding: .65rem .75rem; white-space: nowrap; }
  .theme-options { grid-template-columns: 1fr; }
  .profile-form { margin-top: 0; }
}
</style>
