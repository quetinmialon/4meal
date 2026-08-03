<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import CookbookMessageComposer from '@/components/CookbookMessageComposer.vue';
import CookbookMessageItem from '@/components/CookbookMessageItem.vue';
import { fetchCookbook, fetchCookbookMessages, type Cookbook, type CookbookMessage, type CursorPagination } from '@/utils/cookbooks';

const route = useRoute();
const authStore = useAuthStore();
const cookbook = ref<Cookbook | null>(null);
const messages = ref<CookbookMessage[]>([]);
const pagination = ref<CursorPagination | null>(null);
const errorMessage = ref('');
const loading = ref(true);

async function loadMessages(cursor: string | null = null): Promise<void> {
  loading.value = true;
  errorMessage.value = '';
  const result = await fetchCookbookMessages(String(route.params.id), authStore.tokenType, authStore.accessToken, cursor);
  if (result.ok) {
    messages.value = result.messages;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  loading.value = false;
}

async function loadPage(): Promise<void> {
  loading.value = true;
  errorMessage.value = '';
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);
  if (!result.ok) {
    errorMessage.value = result.message;
    loading.value = false;
    return;
  }
  cookbook.value = result.cookbook;
  await loadMessages();
}

function retry(): void {
  void loadPage();
}

function replaceMessage(message: CookbookMessage): void {
  const index = messages.value.findIndex((item) => item.id === message.id);
  if (index !== -1) messages.value[index] = message;
}

onMounted(() => { void loadPage(); });
</script>

<template>
  <main class="messages-card">
    <RouterLink class="back-link" :to="{ name: 'cookbook', params: { id: route.params.id } }">Retour au cookbook</RouterLink>
    <template v-if="cookbook">
      <p class="kicker">Discussion</p>
      <h2>{{ cookbook.name }}</h2>
      <CookbookMessageComposer
        :cookbook-id="cookbook.id"
        :token-type="authStore.tokenType"
        :access-token="authStore.accessToken"
        @sent="loadMessages"
      />
    </template>
    <p v-if="errorMessage" class="error-summary" role="alert">
      {{ errorMessage }}
      <button type="button" @click="retry">Réessayer</button>
    </p>
    <p v-else-if="loading" role="status">Chargement des messages...</p>
    <p v-else-if="messages.length === 0" class="empty-state">Aucun message.</p>
    <section v-else class="message-list" aria-label="Historique des messages">
      <CookbookMessageItem v-for="message in messages" :key="message.id" :message="message" :cookbook-id="cookbook!.id" :current-user-id="authStore.user?.id ?? null" :current-user-role="cookbook!.member_role" :token-type="authStore.tokenType" :access-token="authStore.accessToken" @updated="replaceMessage" @deleted="replaceMessage" />
      <nav v-if="pagination" class="pagination" aria-label="Pagination des messages">
        <button type="button" :disabled="!pagination.previous_cursor || loading" @click="loadMessages(pagination?.previous_cursor ?? null)">Précédent</button>
        <span>20 messages par page</span>
        <button type="button" :disabled="!pagination.next_cursor || loading" @click="loadMessages(pagination?.next_cursor ?? null)">Suivant</button>
      </nav>
    </section>
  </main>
</template>

<style scoped>
.messages-card { margin: 0 auto; max-width: 42rem; padding: 2rem; border: 1px solid rgba(86, 112, 79, .18); border-radius: 1.5rem; background: rgba(255, 253, 248, .92); }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h2 { margin: 0 0 1.5rem; }
.message-list { display: grid; gap: .75rem; }
.pagination { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: 1rem; color: #50634d; font-size: .85rem; }
.pagination button { padding: .5rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: .45; }
.error-summary { color: #8f1e1e; }
.empty-state { color: #50634d; }
</style>
