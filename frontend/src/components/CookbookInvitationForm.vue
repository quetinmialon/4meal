<script setup lang="ts">
import { ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { createCookbookInvitation } from '@/utils/cookbooks';

const props = defineProps<{ cookbookId: string }>();
const authStore = useAuthStore();
const email = ref('');
const role = ref<'editor' | 'reader'>('reader');
const errorMessage = ref('');
const emailError = ref('');
const roleError = ref('');
const successMessage = ref('');
const isSubmitting = ref(false);

async function submit(): Promise<void> {
  errorMessage.value = '';
  emailError.value = '';
  roleError.value = '';
  successMessage.value = '';
  if (email.value.trim() === '') {
    emailError.value = 'L’adresse email est requise.';
    return;
  }

  isSubmitting.value = true;
  const result = await createCookbookInvitation(props.cookbookId, email.value, role.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    successMessage.value = `Invitation envoyée à ${result.invitation.email}.`;
    email.value = '';
  } else {
    errorMessage.value = result.message;
    emailError.value = result.fieldErrors.email ?? '';
    roleError.value = result.fieldErrors.role ?? '';
  }
  isSubmitting.value = false;
}
</script>

<template>
  <section class="invitation-section" aria-labelledby="invitation-title">
    <h3 id="invitation-title">Inviter un membre</h3>
    <form class="invitation-form" novalidate @submit.prevent="submit">
      <label for="invitation-email">Adresse email</label>
      <input id="invitation-email" v-model="email" type="email" autocomplete="email" placeholder="membre@example.com" :disabled="isSubmitting" :aria-invalid="emailError ? 'true' : 'false'" />
      <p v-if="emailError" class="field-error" role="alert">{{ emailError }}</p>
      <label for="invitation-role">Rôle proposé</label>
      <select id="invitation-role" v-model="role" :disabled="isSubmitting">
        <option value="reader">Lecteur</option>
        <option value="editor">Éditeur</option>
      </select>
      <p v-if="roleError" class="field-error" role="alert">{{ roleError }}</p>
      <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
      <p v-if="successMessage" class="success-summary" role="status">{{ successMessage }}</p>
      <button type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Envoi...' : 'Envoyer l’invitation' }}</button>
    </form>
  </section>
</template>

<style scoped>
.invitation-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.invitation-section h3 { margin: 0 0 1rem; }
.invitation-form { display: grid; gap: 0.55rem; }
.invitation-form label { font-weight: 700; }
.invitation-form input, .invitation-form select { padding: 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; background: #fffdf8; font: inherit; }
.invitation-form button { justify-self: start; margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid #395330; border-radius: 0.5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.invitation-form button:disabled { cursor: wait; opacity: 0.6; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; }
.success-summary { margin: 0; color: #395330; font-weight: 700; }
</style>
