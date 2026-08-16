<script setup lang="ts">
import { ref, watch } from 'vue';

import { addCookbookMessageReaction, deleteCookbookMessage, removeCookbookMessageReaction, updateCookbookMessage, type CookbookMessage, type CookbookMessageReaction } from '@/utils/cookbooks';
import { useDialogFocus } from '@/utils/dialogFocus';

const props = defineProps<{ message: CookbookMessage; cookbookId: string; currentUserId: number | null; currentUserRole: string | null; tokenType: string; accessToken: string }>();
const emit = defineEmits<{ updated: [message: CookbookMessage]; deleted: [message: CookbookMessage] }>();
const editing = ref(false);
const draft = ref(props.message.content);
const confirmation = ref(false);
const error = ref('');
const saving = ref(false);
const confirmationDialog = ref<HTMLElement | null>(null);
const allowedEmojis = ['👍', '❤️', '😂', '😮', '😢', '😡'];
const reactions = ref<CookbookMessageReaction[]>([...(props.message.reactions ?? [])]);
const reactingEmoji = ref<string | null>(null);

watch(() => props.message.reactions, (value) => {
  if (reactingEmoji.value === null) reactions.value = [...(value ?? [])];
}, { deep: true });

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

function reactionFor(emoji: string): CookbookMessageReaction {
  return reactions.value.find((reaction) => reaction.emoji === emoji) ?? { emoji, count: 0, reacted: false };
}

function updateReaction(emoji: string, reacted: boolean): void {
  const current = reactionFor(emoji);
  const nextCount = Math.max(0, current.count + (reacted ? 1 : -1));
  reactions.value = nextCount === 0
    ? reactions.value.filter((reaction) => reaction.emoji !== emoji)
    : [...reactions.value.filter((reaction) => reaction.emoji !== emoji), { emoji, count: nextCount, reacted }];
}

async function toggleReaction(emoji: string): Promise<void> {
  if (reactingEmoji.value !== null) return;
  const reacted = reactionFor(emoji).reacted;
  reactingEmoji.value = emoji;
  error.value = '';
  updateReaction(emoji, !reacted);

  const result = reacted
    ? await removeCookbookMessageReaction(props.cookbookId, props.message.id, emoji, props.tokenType, props.accessToken)
    : await addCookbookMessageReaction(props.cookbookId, props.message.id, emoji, props.tokenType, props.accessToken);

  if (!result.ok) {
    updateReaction(emoji, reacted);
    error.value = result.message;
  }
  reactingEmoji.value = null;
}
</script>

<template>
  <article class="message-item" :class="{ 'message-deleted': message.is_deleted, 'message-current-user': message.author.id === currentUserId }">
    <img v-if="message.author.avatar_url" class="avatar" :src="message.author.avatar_url" :alt="`Avatar de ${message.author.name}`" />
    <div v-else class="avatar avatar-fallback" aria-hidden="true">{{ message.author.name.charAt(0).toUpperCase() }}</div>
    <div class="message-body">
      <div class="message-meta"><strong>{{ message.author.name }}</strong><span v-if="message.author.id === currentUserId" class="current-user-label">Vous</span><span>{{ message.author.role }}</span><time :datetime="message.created_at ?? undefined">{{ message.created_at ? new Date(message.created_at).toLocaleString() : '' }}</time></div>
      <form v-if="editing" class="message-edit-form" @submit.prevent="save">
        <textarea v-model="draft" rows="3" maxlength="2000" :disabled="saving" aria-label="Modifier le message" />
        <p v-if="error" class="error-summary" role="alert">{{ error }}</p>
        <button type="submit" :disabled="saving">Enregistrer</button><button type="button" :disabled="saving" @click="editing = false">Annuler</button>
      </form>
      <template v-else>
        <p>{{ message.content }} <small v-if="message.edited_at && !message.is_deleted">(modifié)</small></p>
        <div v-if="!message.is_deleted" class="message-reactions">
          <div class="reaction-list" aria-label="Réactions au message">
            <button
              v-for="reaction in reactions"
              :key="reaction.emoji"
              type="button"
              class="reaction-counter"
              :aria-label="`${reaction.emoji}, ${reaction.count} réaction${reaction.count > 1 ? 's' : ''}${reaction.reacted ? ', sélectionnée' : ''}`"
              :aria-pressed="reaction.reacted"
              :disabled="reactingEmoji !== null"
              @click="toggleReaction(reaction.emoji)"
            >
              <span aria-hidden="true">{{ reaction.emoji }}</span> {{ reaction.count }}
            </button>
          </div>
          <details class="reaction-picker">
            <summary>Ajouter une réaction</summary>
            <div class="reaction-options" role="group" aria-label="Choisir une réaction">
              <button
                v-for="emoji in allowedEmojis"
                :key="emoji"
                type="button"
                class="reaction-option"
                :aria-label="`${reactionFor(emoji).reacted ? 'Retirer' : 'Ajouter'} la réaction ${emoji}`"
                :aria-pressed="reactionFor(emoji).reacted"
                :disabled="reactingEmoji !== null"
                @click="toggleReaction(emoji)"
              >
                <span aria-hidden="true">{{ emoji }}</span>
              </button>
            </div>
          </details>
        </div>
        <p v-if="error && !editing && !confirmation" class="error-summary" role="alert">{{ error }}</p>
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
.message-current-user { border-inline-start: .3rem solid #395330; }
.message-deleted { opacity: .72; }
.avatar { flex: 0 0 2.5rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; }
.avatar-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.message-body { min-width: 0; flex: 1; }.message-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: baseline; color: #50634d; font-size: .85rem; }.message-meta time { margin-left: auto; }.current-user-label { color: #243127; font-weight: 700; }
.message-body p { margin: .45rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }.message-actions, .message-confirmation { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .55rem; }.message-actions button, .message-confirmation button, .message-edit-form button { padding: .4rem .6rem; border: 1px solid #395330; border-radius: .4rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }.message-edit-form { display: grid; gap: .5rem; margin-top: .5rem; }.message-edit-form textarea { padding: .6rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }.message-confirmation { padding: .6rem; border: 1px solid #e2b3ad; border-radius: .5rem; color: #6d4140; }.error-summary { margin: .3rem 0 0; color: #8f1e1e; }
.message-reactions { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; margin-top: .7rem; }
.reaction-list, .reaction-options { display: flex; flex-wrap: wrap; gap: .35rem; }
.reaction-counter, .reaction-option, .reaction-picker summary { border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; font: inherit; cursor: pointer; }
.reaction-counter, .reaction-option { padding: .3rem .5rem; }
.reaction-counter[aria-pressed="true"], .reaction-option[aria-pressed="true"] { background: #edf4e8; border-color: #395330; }
.reaction-counter:focus-visible, .reaction-option:focus-visible, .reaction-picker summary:focus-visible { outline: 2px solid #395330; outline-offset: 2px; }
.reaction-picker summary { padding: .3rem .5rem; list-style: none; }
.reaction-picker summary::-webkit-details-marker { display: none; }
.reaction-picker[open] summary { margin-bottom: .35rem; }
.reaction-counter:disabled, .reaction-option:disabled { cursor: wait; opacity: .6; }
</style>
