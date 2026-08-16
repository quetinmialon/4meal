<script setup lang="ts">
import { ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { createCookbookInvitation } from '@/utils/cookbooks';
import { useDialogFocus } from '@/utils/dialogFocus';

const props = defineProps<{ cookbookId: string }>();
const authStore = useAuthStore();
const dialog = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const email = ref('');
const role = ref<'editor' | 'reader'>('reader');
const errorMessage = ref('');
const emailError = ref('');
const roleError = ref('');
const successMessage = ref('');
const isSubmitting = ref(false);

function resetMessages(): void {
  errorMessage.value = '';
  emailError.value = '';
  roleError.value = '';
  successMessage.value = '';
}

function openDialog(): void {
  resetMessages();
  isOpen.value = true;
}

function closeDialog(): void {
  if (isSubmitting.value) return;
  isOpen.value = false;
}

async function submit(): Promise<void> {
  resetMessages();
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

useDialogFocus(dialog, isOpen, closeDialog);
</script>

<template>
  <section class="invitation-section" aria-labelledby="invitation-section-title">
    <div class="invitation-heading">
      <div>
        <h3 id="invitation-section-title">Inviter un membre</h3>
        <p>Envoyez une invitation avec le niveau d’accès approprié.</p>
      </div>
      <button type="button" class="invite-button" @click="openDialog">Inviter</button>
    </div>

    <div v-show="isOpen" class="invitation-backdrop" @click.self="closeDialog">
      <section
        ref="dialog"
        class="invitation-dialog"
        :role="isOpen ? 'dialog' : undefined"
        :aria-modal="isOpen ? 'true' : undefined"
        aria-labelledby="invitation-title"
        aria-describedby="invitation-description"
        tabindex="-1"
      >
        <div class="invitation-dialog-heading">
          <div>
            <h3 id="invitation-title">Inviter un membre</h3>
            <p id="invitation-description">Indiquez le destinataire et les permissions à proposer.</p>
          </div>
          <button type="button" class="close-button" aria-label="Fermer la fenêtre d’invitation" :disabled="isSubmitting" @click="closeDialog">×</button>
        </div>

        <form class="invitation-form" novalidate @submit.prevent="submit">
          <label for="invitation-email">Adresse email du destinataire</label>
          <input id="invitation-email" v-model="email" type="email" autocomplete="email" placeholder="membre@example.com" :disabled="isSubmitting" :aria-invalid="emailError ? 'true' : 'false'" :aria-describedby="emailError ? 'invitation-email-error' : undefined" />
          <p v-if="emailError" id="invitation-email-error" class="field-error" role="alert">{{ emailError }}</p>
          <label for="invitation-role">Rôle proposé</label>
          <select id="invitation-role" v-model="role" :disabled="isSubmitting" :aria-invalid="roleError ? 'true' : 'false'" :aria-describedby="roleError ? 'invitation-role-error' : undefined">
            <option value="reader">Lecteur</option>
            <option value="editor">Éditeur</option>
          </select>
          <p v-if="roleError" id="invitation-role-error" class="field-error" role="alert">{{ roleError }}</p>
          <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
          <p v-if="successMessage" class="success-summary" role="status" aria-live="polite">{{ successMessage }}</p>
          <div class="invitation-actions">
            <button type="button" class="cancel-button" :disabled="isSubmitting" @click="closeDialog">Annuler</button>
            <button type="submit" class="submit-button" :disabled="isSubmitting">{{ isSubmitting ? 'Invitation en cours...' : 'Inviter' }}</button>
          </div>
        </form>
      </section>
    </div>
  </section>
</template>

<style scoped>
.invitation-section { margin-top: 2rem; padding: 1.25rem 0 0; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.invitation-heading, .invitation-dialog-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.invitation-heading h3, .invitation-dialog h3 { margin: 0; }
.invitation-heading p, .invitation-dialog-heading p { margin: .35rem 0 0; color: #50634d; line-height: 1.45; }
.invite-button, .submit-button { padding: .65rem .85rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.invitation-backdrop { position: fixed; inset: 0; z-index: 30; display: grid; place-items: center; padding: 1rem; background: rgba(29, 39, 24, .45); }
.invitation-dialog { width: min(100%, 32rem); max-height: min(100%, 42rem); overflow: auto; padding: 1.5rem; border: 1px solid rgba(86, 112, 79, .25); border-radius: 1rem; background: #fffdf8; box-shadow: 0 20px 60px rgba(54, 68, 35, .2); }
.invitation-form { display: grid; gap: .55rem; margin-top: 1.25rem; }
.invitation-form label { font-weight: 700; }
.invitation-form input, .invitation-form select { box-sizing: border-box; width: 100%; padding: .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: #fffdf8; font: inherit; }
.close-button { min-width: 2.5rem; min-height: 2.5rem; padding: .25rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; font-size: 1.5rem; line-height: 1; cursor: pointer; }
.invitation-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: .65rem; }
.cancel-button { padding: .65rem .85rem; border: 1px solid #395330; border-radius: .5rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; }
.success-summary { margin: 0; color: #395330; font-weight: 700; }
button:disabled, input:disabled, select:disabled { cursor: wait; opacity: .6; }
button:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid #d98b35; outline-offset: 2px; }
@media (max-width: 36rem) { .invitation-heading { align-items: flex-start; flex-direction: column; } .invitation-heading .invite-button { width: 100%; } .invitation-dialog { padding: 1.15rem; } .invitation-actions { flex-direction: column-reverse; } .invitation-actions button { width: 100%; } }
</style>
