<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import CookbookMessageComposer from '@/components/CookbookMessageComposer.vue';
import CookbookMessageItem from '@/components/CookbookMessageItem.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useAuthStore } from '@/stores/auth';
import { useRealtimeStore } from '@/stores/realtime';
import { fetchCookbook, fetchCookbookMessages, type Cookbook, type CookbookMessage, type CursorPagination } from '@/utils/cookbooks';

const route = useRoute();
const authStore = useAuthStore();
const realtimeStore = useRealtimeStore();
const cookbook = ref<Cookbook | null>(null);
const messages = computed(() => realtimeStore.messagesByCookbook[String(route.params.id)] ?? []);
const pagination = ref<CursorPagination | null>(null);
const errorMessage = ref('');
const loading = ref(true);

async function loadMessages(cursor: string | null = null): Promise<void> {
  loading.value = true;
  errorMessage.value = '';
  const result = await fetchCookbookMessages(String(route.params.id), authStore.tokenType, authStore.accessToken, cursor);
  if (result.ok) {
    realtimeStore.setMessages(String(route.params.id), result.messages);
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
  realtimeStore.upsertMessage(String(route.params.id), message);
}

watch(() => String(route.params.id), () => { void loadPage(); }, { immediate: true });
</script>

<template>
  <main class="messages-card">
    <RouterLink class="back-link" :to="{ name: 'cookbook', params: { id: route.params.id } }">Retour au cookbook</RouterLink>
    <template v-if="cookbook">
      <p class="kicker">Discussion</p>
      <div class="discussion-heading">
        <div>
          <h2>{{ cookbook.name }}</h2>
          <p class="discussion-context">Échangez avec les membres de ce cookbook.</p>
        </div>
        <span class="role-badge">{{ cookbook.member_role ?? 'Membre' }}</span>
      </div>
      <CookbookMessageComposer
        :cookbook-id="cookbook.id"
        :token-type="authStore.tokenType"
        :access-token="authStore.accessToken"
        @sent="loadMessages"
      />
    </template>
    <ErrorState v-if="errorMessage" :message="errorMessage" show-retry @retry="retry" />
    <LoadingState v-else-if="loading" label="Chargement des messages..." />
    <EmptyState v-else-if="messages.length === 0" title="Aucun message dans cette discussion." description="Soyez le premier à lancer la conversation." />
    <section v-else class="message-history" aria-labelledby="message-history-title">
      <h3 id="message-history-title" class="sr-only">Historique des messages</h3>
      <div class="message-list">
        <CookbookMessageItem v-for="message in messages" :key="message.id" :message="message" :cookbook-id="cookbook!.id" :current-user-id="authStore.user?.id ?? null" :current-user-role="cookbook!.member_role" :token-type="authStore.tokenType" :access-token="authStore.accessToken" @updated="replaceMessage" @deleted="replaceMessage" />
      </div>
      <nav v-if="pagination" class="pagination" aria-label="Pagination des messages">
        <button type="button" :disabled="!pagination.previous_cursor || loading" @click="loadMessages(pagination?.previous_cursor ?? null)">Précédent</button>
        <span>20 messages par page</span>
        <button type="button" :disabled="!pagination.next_cursor || loading" @click="loadMessages(pagination?.next_cursor ?? null)">Suivant</button>
      </nav>
    </section>
  </main>
</template>

<style scoped>
.messages-card { width: 100%; max-width: 76rem; margin: 0 auto; padding: 2rem; box-sizing: border-box; border: 1px solid rgba(86, 112, 79, .18); border-radius: 1.5rem; background: rgba(255, 253, 248, .92); }
.back-link { color: #395330; font-weight: 700; }
.kicker { margin: 2rem 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
.discussion-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
h2 { margin: 0; }
.discussion-context { margin: .35rem 0 0; color: #50634d; line-height: 1.45; }
.role-badge { padding: .3rem .55rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: .85rem; font-weight: 700; }
.message-history { margin-top: 1.5rem; scroll-margin-top: 7rem; }
.message-list { display: grid; gap: .75rem; }
.pagination { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: 1rem; color: #50634d; font-size: .85rem; }
.pagination button { padding: .5rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: .45; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 36rem) { .discussion-heading { align-items: flex-start; flex-direction: column; } .messages-card { padding: 1rem; } }
</style>
