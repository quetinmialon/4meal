<script setup lang="ts">
import { ref } from 'vue';

import { sendCookbookMessage } from '@/utils/cookbooks';

const props = defineProps<{
  cookbookId: string;
  tokenType: string;
  accessToken: string;
}>();
const emit = defineEmits<{ sent: [] }>();
const content = ref('');
const errorMessage = ref('');
const fieldError = ref('');
const sending = ref(false);

async function submit(): Promise<void> {
  errorMessage.value = '';
  fieldError.value = '';
  const trimmed = content.value.trim();
  if (trimmed.length === 0) {
    fieldError.value = 'Le message est requis.';
    return;
  }
  if (trimmed.length > 2000) {
    fieldError.value = 'Le message ne peut pas dépasser 2000 caractères.';
    return;
  }

  sending.value = true;
  const result = await sendCookbookMessage(props.cookbookId, trimmed, props.tokenType, props.accessToken);
  if (result.ok) {
    content.value = '';
    emit('sent');
  } else {
    errorMessage.value = result.message;
    fieldError.value = result.fieldError ?? '';
  }
  sending.value = false;
}
</script>

<template>
  <form class="message-composer" @submit.prevent="submit">
    <label for="cookbook-message-content">Votre message</label>
    <textarea
      id="cookbook-message-content"
      v-model="content"
      rows="3"
      maxlength="2000"
      :disabled="sending"
      :aria-invalid="fieldError ? 'true' : 'false'"
      :aria-describedby="fieldError ? 'cookbook-message-error' : undefined"
      placeholder="Écrire un message..."
    />
    <p v-if="fieldError" id="cookbook-message-error" class="field-error" role="alert">{{ fieldError }}</p>
    <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
    <div class="composer-actions">
      <span>{{ content.length }}/2000</span>
      <button type="submit" :disabled="sending || content.trim().length === 0">
        {{ sending ? 'Envoi...' : 'Envoyer' }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.message-composer { display: grid; gap: .55rem; margin: 1rem 0; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; }
.message-composer label { font-weight: 700; }
.message-composer textarea { resize: vertical; padding: .7rem; border: 1px solid #b9c5af; border-radius: .5rem; font: inherit; }
.composer-actions { display: flex; justify-content: space-between; align-items: center; color: #50634d; font-size: .85rem; }
.composer-actions button { padding: .5rem .7rem; border: 1px solid #395330; border-radius: .5rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; cursor: pointer; }
.composer-actions button:disabled { cursor: not-allowed; opacity: .5; }
.field-error, .error-summary { margin: 0; color: #8f1e1e; }
</style>
