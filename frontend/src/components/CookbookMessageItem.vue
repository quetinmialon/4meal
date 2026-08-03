<script setup lang="ts">
import { ref } from 'vue';

import { deleteCookbookMessage, updateCookbookMessage, type CookbookMessage } from '@/utils/cookbooks';
import { useDialogFocus } from '@/utils/dialogFocus';

const props = defineProps<{ message: CookbookMessage; cookbookId: string; currentUserId: number | null; currentUserRole: string | null; tokenType: string; accessToken: string }>();
const emit = defineEmits<{ updated: [message: CookbookMessage]; deleted: [message: CookbookMessage] }>();
const editing = ref(false);
const draft = ref(props.message.content);
const confirmation = ref(false);
const error = ref('');
const saving = ref(false);
const confirmationDialog = ref<HTMLElement | null>(null);

const canEdit = () => !props.message.is_deleted && props.message.author.id === props.currentUserId;
const canDelete = () => !props.message.is_deleted && (props.message.author.id === props.currentUserId || props.currentUserRole === 'owner' || props.currentUserRole === 'moderator');

useDialogFocus(confirmationDialog, confirmation, () => {
  if (!saving.value) confirmation.value = false;
});

async function save(): Promise<void> {
  error.value = '';
  if (draft.value.trim().length === 0 || draft.value.trim().length > 2000) { error.value = 'Le message doit contenir entre 1 et 2000 caractères.'; return; }
  saving.value = true;
  const result = await updateCookbookMessage(props.cookbookId, props.message.id, draft.value, props.tokenType, props.accessToken);
  if (result.ok) { editing.value = false; emit('updated', result.message); } else error.value = result.fieldError ?? result.message;
  saving.value = false;
}

async function remove(): Promise<void> {
  error.value = '';
  saving.value = true;
  const result = await deleteCookbookMessage(props.cookbookId, props.message.id, props.tokenType, props.accessToken);
  if (result.ok) { confirmation.value = false; emit('deleted', result.message); } else error.value = result.message;
  saving.value = false;
}
</script>

<template>
  <article class="message-item" :class="{ 'message-deleted': message.is_deleted }">
    <img v-if="message.author.avatar_url" class="avatar" :src="message.author.avatar_url" :alt="`Avatar de ${message.author.name}`" />
    <div v-else class="avatar avatar-fallback" aria-hidden="true">{{ message.author.name.charAt(0).toUpperCase() }}</div>
    <div class="message-body">
      <div class="message-meta"><strong>{{ message.author.name }}</strong><span>{{ message.author.role }}</span><time :datetime="message.created_at ?? undefined">{{ message.created_at ? new Date(message.created_at).toLocaleString() : '' }}</time></div>
      <form v-if="editing" class="message-edit-form" @submit.prevent="save">
        <textarea v-model="draft" rows="3" maxlength="2000" :disabled="saving" aria-label="Modifier le message" />
        <p v-if="error" class="error-summary" role="alert">{{ error }}</p>
        <button type="submit" :disabled="saving">Enregistrer</button><button type="button" :disabled="saving" @click="editing = false">Annuler</button>
      </form>
      <template v-else>
        <p>{{ message.content }} <small v-if="message.edited_at && !message.is_deleted">(modifié)</small></p>
        <div v-if="canEdit() || canDelete()" class="message-actions">
          <button v-if="canEdit()" type="button" @click="editing = true; draft = message.content; error = ''">Modifier</button>
          <button v-if="canDelete()" type="button" @click="confirmation = true; error = ''">Supprimer</button>
        </div>
        <div v-if="confirmation" ref="confirmationDialog" class="message-confirmation" role="alertdialog" aria-labelledby="message-delete-title" tabindex="-1">
          <span id="message-delete-title">Supprimer ce message ?</span><button type="button" :disabled="saving" @click="remove">Confirmer</button><button type="button" :disabled="saving" @click="confirmation = false">Annuler</button>
          <p v-if="error" class="error-summary" role="alert">{{ error }}</p>
        </div>
      </template>
    </div>
  </article>
</template>

<style scoped>
.message-item { display: flex; gap: .75rem; padding: 1rem; border: 1px solid rgba(86,112,79,.2); border-radius: .8rem; }
.message-deleted { opacity: .72; }
.avatar { flex: 0 0 2.5rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; }
.avatar-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.message-body { min-width: 0; flex: 1; }.message-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: baseline; color: #50634d; font-size: .85rem; }.message-meta time { margin-left: auto; }
.message-body p { margin: .45rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }.message-actions, .message-confirmation { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .55rem; }.message-actions button, .message-confirmation button, .message-edit-form button { padding: .4rem .6rem; border: 1px solid #395330; border-radius: .4rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }.message-edit-form { display: grid; gap: .5rem; margin-top: .5rem; }.message-edit-form textarea { padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }.message-confirmation { padding: .6rem; border: 1px solid #e2b3ad; border-radius: .5rem; color: #6d4140; }.error-summary { margin: .3rem 0 0; color: #8f1e1e; }
</style>
