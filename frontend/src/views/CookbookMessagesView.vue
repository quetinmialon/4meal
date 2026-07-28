<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import CookbookMessageComposer from '@/components/CookbookMessageComposer.vue';
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
  const result = await fetchCookbookMessages(String(route.params.id), authStore.tokenType, authStore.accessToken, cursor);
  if (result.ok) {
    messages.value = result.messages;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  loading.value = false;
}

onMounted(async () => {
  const result = await fetchCookbook(String(route.params.id), authStore.tokenType, authStore.accessToken);
  if (!result.ok) {
    errorMessage.value = result.message;
    loading.value = false;
    return;
  }
  cookbook.value = result.cookbook;
  await loadMessages();
});
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
    <p v-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
    <p v-else-if="loading" role="status">Chargement des messages...</p>
    <p v-else-if="messages.length === 0" class="empty-state">Aucun message.</p>
    <section v-else class="message-list" aria-label="Historique des messages">
      <article v-for="message in messages" :key="message.id" class="message-item">
        <img v-if="message.author.avatar_url" class="avatar" :src="message.author.avatar_url" :alt="`Avatar de ${message.author.name}`" />
        <div v-else class="avatar avatar-fallback" aria-hidden="true">{{ message.author.name.charAt(0).toUpperCase() }}</div>
        <div class="message-body">
          <div class="message-meta">
            <strong>{{ message.author.name }}</strong>
            <span>{{ message.author.role }}</span>
            <time :datetime="message.created_at ?? undefined">{{ message.created_at ? new Date(message.created_at).toLocaleString() : '' }}</time>
          </div>
          <p>{{ message.content }}</p>
        </div>
      </article>
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
.message-item { display: flex; gap: .75rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; }
.avatar { flex: 0 0 2.5rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; }
.avatar-fallback { display: grid; place-items: center; background: #edf4e8; color: #395330; font-weight: 700; }
.message-body { min-width: 0; flex: 1; }
.message-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: baseline; color: #50634d; font-size: .85rem; }
.message-meta strong { color: #263b22; }
.message-meta time { margin-left: auto; }
.message-body p { margin: .45rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
.pagination { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: 1rem; color: #50634d; font-size: .85rem; }
.pagination button { padding: .5rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: .45; }
.error-summary { color: #8f1e1e; }
.empty-state { color: #50634d; }
</style>
