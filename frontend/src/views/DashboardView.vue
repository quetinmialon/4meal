<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import CookbookCreateForm from '@/components/CookbookCreateForm.vue';
import { useAuthStore } from '@/stores/auth';
import type { Cookbook, CookbookInvitation, Pagination } from '@/utils/cookbooks';
import { acceptCookbookInvitationById, declineCookbookInvitation, fetchCookbookInvitations, fetchCookbooks } from '@/utils/cookbooks';

const authStore = useAuthStore();
const router = useRouter();

const userName = computed(() => authStore.user?.name ?? '');
const userEmail = computed(() => authStore.user?.email ?? '');
const isCreateFormVisible = ref(false);
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

async function handleLogout(): Promise<void> {
  authStore.clearSession();
  await router.push({ name: 'login' });
}
</script>

<template>
  <main class="dashboard-card">
    <p class="kicker">Mon espace</p>
    <h2>Connexion reussie</h2>
    <p class="message">
      <span v-if="userName">Bienvenue, {{ userName }}.</span>
      <span v-else>Vous etes bien connecte.</span>
    </p>
    <p class="detail">Votre espace est accessible. Vous pouvez continuer en toute simplicite.</p>
    <p v-if="userEmail" class="meta">Compte associe : {{ userEmail }}.</p>
    <section class="invitations-section" aria-labelledby="invitations-title">
      <div class="section-heading">
        <div>
          <p class="kicker">À traiter</p>
          <h3 id="invitations-title">Invitations reçues</h3>
        </div>
        <span v-if="invitations.length" class="invitation-count">{{ invitations.length }}</span>
      </div>
      <p v-if="invitationsLoading" class="loading" role="status">Chargement des invitations...</p>
      <p v-else-if="invitationsError" class="error-summary" role="alert">{{ invitationsError }}</p>
      <p v-else-if="invitations.length === 0" class="empty-state invitation-empty">Aucune invitation en attente.</p>
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
    <section class="cookbooks-section" aria-labelledby="cookbooks-title">
      <div class="section-heading">
        <div>
          <p class="kicker">Mes recettes</p>
          <h3 id="cookbooks-title">Mes cookbooks</h3>
        </div>
        <div class="dashboard-actions">
          <RouterLink class="create-button" :to="{ name: 'recipes' }">Mes recettes</RouterLink>
          <RouterLink class="create-button" :to="{ name: 'recipe-create' }">Nouvelle recette</RouterLink>
          <button class="create-button" type="button" @click="isCreateFormVisible = !isCreateFormVisible">
            {{ isCreateFormVisible ? 'Fermer' : 'Nouveau cookbook' }}
          </button>
        </div>
      </div>
      <p v-if="isLoading" class="loading" role="status">Chargement des cookbooks...</p>
      <p v-else-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
      <div v-else-if="cookbooks.length === 0 && !isCreateFormVisible" class="empty-state">
        <p>Vous n’avez encore aucun cookbook.</p>
        <p>Créez votre premier espace pour organiser vos recettes.</p>
      </div>
      <div v-else-if="!isCreateFormVisible" class="cookbook-list">
        <RouterLink
          v-for="cookbook in cookbooks"
          :key="cookbook.id"
          class="cookbook-item"
          :to="{ name: 'cookbook', params: { id: cookbook.id } }"
        >
          <span>
            <strong>{{ cookbook.name }}</strong>
            <small>{{ cookbook.owner.name }}</small>
          </span>
          <span class="role-badge">{{ cookbook.member_role ?? 'membre' }}</span>
        </RouterLink>
        <nav v-if="pagination && pagination.last_page > 1" class="pagination" aria-label="Pagination des cookbooks">
          <button type="button" :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">
            Precedent
          </button>
          <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button type="button" :disabled="!pagination.has_more_pages" @click="goToPage(pagination.current_page + 1)">
            Suivant
          </button>
        </nav>
      </div>
      <CookbookCreateForm v-else />
    </section>
    <RouterLink class="password-link" :to="{ name: 'change-password' }">
      Modifier mon mot de passe
    </RouterLink>
    <button class="logout-button" type="button" @click="handleLogout">Deconnexion</button>
  </main>
</template>

<style scoped>
.dashboard-card {
  margin: 0 auto;
  max-width: 42rem;
  padding: 2rem;
  border: 1px solid rgba(86, 112, 79, 0.18);
  border-radius: 1.5rem;
  background: rgba(255, 253, 248, 0.92);
  box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1);
}

.kicker {
  margin: 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #6b7b57;
}

h2 {
  margin: 0 0 1rem;
  font-size: clamp(1.9rem, 4vw, 2.8rem);
  line-height: 1;
}

.message,
.detail {
  margin: 0;
  max-width: 34rem;
  line-height: 1.6;
}

.detail {
  margin-top: 0.75rem;
  color: #50634d;
}

.meta {
  margin: 1.5rem 0 0;
  color: #395330;
  font-weight: 700;
}

.password-link {
  display: inline-flex;
  margin-top: 1.5rem;
  color: #2f4520;
  font-weight: 700;
}

.invitations-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.invitation-count { min-width: 1.7rem; padding: 0.25rem 0.5rem; border-radius: 999px; background: #e6a84e; color: #fffdf8; text-align: center; font-weight: 700; }
.invitation-empty { margin-top: 1rem; }
.invitation-list { display: grid; gap: 0.7rem; margin-top: 1rem; }
.invitation-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; }
.invitation-item strong, .invitation-item small { display: block; }
.invitation-item small { margin-top: 0.25rem; color: #50634d; }
.invitation-item .expired { color: #8f1e1e; font-weight: 700; }
.invitation-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.accept-button, .decline-button { padding: 0.5rem 0.7rem; border-radius: 0.5rem; font: inherit; font-weight: 700; cursor: pointer; }
.accept-button { border: 1px solid #395330; background: #395330; color: #fffdf8; }
.decline-button { border: 1px solid #8f1e1e; background: transparent; color: #8f1e1e; }
.accept-button:disabled, .decline-button:disabled { cursor: wait; opacity: 0.55; }

.cookbooks-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.dashboard-actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
.section-heading .kicker { margin-bottom: 0.35rem; }
h3 { margin: 0; font-size: 1.5rem; }
.create-button { padding: 0.7rem 0.9rem; border: 1px solid #395330; border-radius: 0.65rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
.empty-state { margin-top: 1rem; padding: 1.25rem; border: 1px dashed #b9c5af; border-radius: 0.8rem; color: #50634d; line-height: 1.5; }
.empty-state p { margin: 0; }
.empty-state p + p { margin-top: 0.35rem; }
.loading, .error-summary { margin-top: 1rem; }
.error-summary { color: #8f1e1e; }
.cookbook-list { display: grid; gap: 0.7rem; margin-top: 1rem; }
.cookbook-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, 0.2); border-radius: 0.8rem; color: #243127; text-decoration: none; }
.cookbook-item:hover { border-color: #6b7b57; background: #f7fbf3; }
.cookbook-item strong, .cookbook-item small { display: block; }
.cookbook-item small { margin-top: 0.25rem; color: #50634d; }
.role-badge { padding: 0.35rem 0.6rem; border-radius: 999px; background: #edf4e8; color: #395330; font-size: 0.85rem; font-weight: 700; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.5rem; color: #50634d; font-size: 0.9rem; }
.pagination button { padding: 0.5rem 0.7rem; border: 1px solid #b9c5af; border-radius: 0.5rem; background: transparent; color: #395330; cursor: pointer; }
.pagination button:disabled { cursor: not-allowed; opacity: 0.45; }

.logout-button {
  display: block;
  margin-top: 1rem;
  padding: 0;
  border: 0;
  background: transparent;
  color: #8f1e1e;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.logout-button:focus-visible {
  outline: 3px solid rgba(185, 72, 72, 0.28);
  outline-offset: 3px;
}
</style>
