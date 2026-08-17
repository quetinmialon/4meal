<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import Avatar from '@/components/Avatar.vue';
import Badge from '@/components/Badge.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useAuthStore } from '@/stores/auth';
import type { Cookbook, CookbookInvitation, Pagination } from '@/utils/cookbooks';
import { acceptCookbookInvitationById, declineCookbookInvitation, fetchCookbookInvitations, fetchCookbooks } from '@/utils/cookbooks';

const authStore = useAuthStore();
const userName = computed(() => authStore.user?.name ?? '');
const userEmail = computed(() => authStore.user?.email ?? '');
const cookbooks = ref<Cookbook[]>([]);
const pagination = ref<Pagination | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');
const invitations = ref<CookbookInvitation[]>([]);
const invitationsLoading = ref(true);
const invitationsError = ref('');
const invitationActionError = ref('');
const acceptingInvitationId = ref<number | null>(null);
const decliningInvitationId = ref<number | null>(null);

async function loadCookbooks(page = 1): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchCookbooks(authStore.tokenType, authStore.accessToken, page);

  if (result.ok) {
    cookbooks.value = result.data;
    pagination.value = result.pagination;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

async function goToPage(page: number): Promise<void> {
  if (pagination.value === null || page < 1 || page > pagination.value.last_page) return;
  await loadCookbooks(page);
}

async function loadInvitations(): Promise<void> {
  invitationsLoading.value = true;
  invitationsError.value = '';
  const result = await fetchCookbookInvitations(authStore.tokenType, authStore.accessToken);
  if (result.ok) invitations.value = result.invitations;
  else invitationsError.value = result.message;
  invitationsLoading.value = false;
}

async function acceptInvitation(invitation: CookbookInvitation): Promise<void> {
  acceptingInvitationId.value = invitation.id;
  invitationActionError.value = '';
  const result = await acceptCookbookInvitationById(invitation.id, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    invitations.value = invitations.value.filter((item) => item.id !== invitation.id);
    await loadCookbooks();
  } else invitationActionError.value = result.message;
  acceptingInvitationId.value = null;
}

async function declineInvitation(invitation: CookbookInvitation): Promise<void> {
  decliningInvitationId.value = invitation.id;
  invitationActionError.value = '';
  const result = await declineCookbookInvitation(invitation.id, authStore.tokenType, authStore.accessToken);
  if (result.ok) invitations.value = invitations.value.filter((item) => item.id !== invitation.id);
  else invitationActionError.value = result.message;
  decliningInvitationId.value = null;
}

onMounted(() => { void Promise.all([loadCookbooks(), loadInvitations()]); });
</script>

<template>
  <main class="dashboard-page">
    <PageHeader
      title="Votre espace"
      :description="userName ? `Bienvenue, ${userName}. Retrouvez ici les informations qui nécessitent votre attention.` : 'Retrouvez ici les informations qui nécessitent votre attention.'"
      eyebrow="Accueil"
    >
      <template #primary>
        <RouterLink class="primary-action" :to="{ name: 'recipe-create' }">Nouvelle recette</RouterLink>
      </template>
      <template #actions>
        <RouterLink class="secondary-action" :to="{ name: 'planning' }">Planifier un repas</RouterLink>
      </template>
    </PageHeader>

    <section v-if="userEmail || authStore.user" class="account-summary" aria-labelledby="account-summary-title">
      <Avatar :src="authStore.user?.avatar_url ?? null" :name="userName || userEmail || 'Utilisateur'" size="large" />
      <div>
        <h2 id="account-summary-title">Votre compte</h2>
        <p v-if="userEmail" class="meta">{{ userEmail }}</p>
      </div>
    </section>

    <section class="dashboard-section invitations-section" aria-labelledby="invitations-title">
      <div class="section-heading">
        <div>
          <p class="kicker">À traiter</p>
          <h2 id="invitations-title">Invitations reçues</h2>
        </div>
        <Badge v-if="invitations.length" tone="warning">{{ invitations.length }}</Badge>
      </div>
      <LoadingState v-if="invitationsLoading" label="Chargement des invitations..." />
      <ErrorState v-else-if="invitationsError" :message="invitationsError" />
      <EmptyState v-else-if="invitations.length === 0" title="Aucune invitation en attente." />
      <div v-else class="invitation-list">
        <article v-for="invitation in invitations" :key="invitation.id" class="invitation-item">
          <div>
            <strong>{{ invitation.cookbook.name }}</strong>
            <small>Rôle proposé : {{ invitation.role === 'editor' ? 'éditeur' : 'lecteur' }}</small>
            <small :class="{ expired: new Date(invitation.expires_at).getTime() <= Date.now() }">
              {{ new Date(invitation.expires_at).getTime() <= Date.now() ? 'Invitation expirée' : `Expire le ${new Date(invitation.expires_at).toLocaleDateString('fr-FR')}` }}
            </small>
          </div>
          <div v-if="new Date(invitation.expires_at).getTime() > Date.now()" class="invitation-actions">
            <button type="button" class="accept-button" :disabled="acceptingInvitationId !== null || decliningInvitationId !== null" @click="acceptInvitation(invitation)">
              {{ acceptingInvitationId === invitation.id ? 'Acceptation...' : 'Accepter' }}
            </button>
            <button type="button" class="decline-button" :disabled="acceptingInvitationId !== null || decliningInvitationId !== null" @click="declineInvitation(invitation)">
              {{ decliningInvitationId === invitation.id ? 'Refus...' : 'Refuser' }}
            </button>
          </div>
        </article>
      </div>
      <p v-if="invitationActionError" class="error-summary" role="alert">{{ invitationActionError }}</p>
    </section>

    <section class="dashboard-section cookbooks-section" aria-labelledby="cookbooks-title">
      <div class="section-heading">
        <div>
          <p class="kicker">Espaces partagés</p>
          <h2 id="cookbooks-title">Mes cookbooks</h2>
        </div>
        <RouterLink class="secondary-action" :to="{ name: 'cookbooks' }">Nouveau cookbook</RouterLink>
      </div>
      <LoadingState v-if="isLoading" label="Chargement des cookbooks..." />
      <ErrorState v-else-if="errorMessage" :message="errorMessage" />
      <EmptyState v-else-if="cookbooks.length === 0" title="Vous n’avez encore aucun cookbook." description="Créez votre premier espace pour organiser vos recettes." />
      <div v-else class="cookbook-list">
        <RouterLink
          v-for="cookbook in cookbooks"
          :key="cookbook.id"
          class="cookbook-item"
          :to="{ name: 'cookbook', params: { id: cookbook.id } }"
        >
          <span class="cookbook-identity">
            <Avatar :src="cookbook.owner.avatar_url ?? null" :name="cookbook.owner.name" size="small" />
            <span>
              <strong>{{ cookbook.name }}</strong>
              <small>{{ cookbook.owner.name }}</small>
            </span>
          </span>
          <Badge class="role-badge">{{ cookbook.member_role ?? 'membre' }}</Badge>
        </RouterLink>
        <nav v-if="pagination && pagination.last_page > 1" class="pagination" aria-label="Pagination des cookbooks">
          <button type="button" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">Précédent</button>
          <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button type="button" :disabled="!pagination.has_more_pages" @click="goToPage(pagination.current_page + 1)">Suivant</button>
        </nav>
      </div>
    </section>
  </main>
</template>

<style scoped>
.dashboard-page { width: 100%; max-width: 76rem; margin: 0 auto; }
.dashboard-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, .18); }
.account-summary { display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .18); border-radius: .9rem; background: rgba(255, 253, 248, .72); }
.account-summary h2, .section-heading h2 { margin: 0; font-size: 1.45rem; }
.meta { margin: .35rem 0 0; color: #50634d; }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.primary-action, .secondary-action { display: inline-flex; align-items: center; justify-content: center; min-height: 2.7rem; padding: .65rem .85rem; border-radius: .65rem; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
.primary-action { border: 1px solid #395330; background: #395330; color: #fffdf8; }
.secondary-action { border: 1px solid #395330; background: transparent; color: #395330; }
.section-link { color: #395330; font-weight: 700; }
.invitation-list, .cookbook-list { display: grid; gap: .7rem; margin-top: 1rem; }
.invitation-item, .cookbook-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; }
.invitation-item strong, .invitation-item small, .cookbook-item strong, .cookbook-item small { display: block; }
.invitation-item small, .cookbook-item small { margin-top: .25rem; color: #50634d; }
.invitation-item .expired { color: #8f1e1e; font-weight: 700; }
.invitation-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.accept-button, .decline-button { padding: .5rem .7rem; border-radius: .5rem; font: inherit; font-weight: 700; cursor: pointer; }
.accept-button { border: 1px solid #395330; background: #395330; color: #fffdf8; }
.decline-button { border: 1px solid #8f1e1e; background: transparent; color: #8f1e1e; }
.accept-button:disabled, .decline-button:disabled { cursor: wait; opacity: .55; }
.cookbook-item { color: #243127; text-decoration: none; }
.cookbook-item:hover { border-color: #6b7b57; background: #f7fbf3; }
.cookbook-identity { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .5rem; color: #50634d; font-size: .9rem; }
.pagination button { padding: .5rem .7rem; border: 1px solid #b9c5af; border-radius: .5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: .45; }
.error-summary { margin-top: 1rem; color: #8f1e1e; }
@media (max-width: 42rem) {
  .section-heading { align-items: flex-start; flex-direction: column; }
  .section-heading > .secondary-action, .section-link { align-self: stretch; text-align: center; }
  .invitation-item, .cookbook-item { align-items: flex-start; flex-direction: column; }
  .invitation-actions { width: 100%; }
  .invitation-actions button { flex: 1; }
}
</style>
