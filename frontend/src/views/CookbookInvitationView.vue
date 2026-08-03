<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import { acceptCookbookInvitation, fetchCookbookInvitation, type CookbookInvitation, type CookbookRole } from '@/utils/cookbooks';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const invitation = ref<CookbookInvitation | null>(null);
const errorMessage = ref('');
const isExpired = ref(false);
const isLoading = ref(true);
const isAccepting = ref(false);
const accepted = ref(false);
const acceptError = ref('');
const acceptedCookbook = ref<{ id: string; name: string; role: CookbookRole } | null>(null);
const token = computed(() => String(route.params.token ?? ''));
const isAuthenticated = computed(() => authStore.isAuthenticated);
const loginTarget = computed(() => ({ name: 'login', query: { redirect: `/invitations/${encodeURIComponent(token.value)}` } }));

async function loadInvitation(): Promise<void> {
  const result = await fetchCookbookInvitation(token.value);
  if (result.ok) invitation.value = result.invitation;
  else { errorMessage.value = result.message; isExpired.value = result.expired === true; }
  isLoading.value = false;
}

async function accept(): Promise<void> {
  if (!isAuthenticated.value) return;
  isAccepting.value = true;
  acceptError.value = '';
  const result = await acceptCookbookInvitation(token.value, authStore.tokenType, authStore.accessToken);
  if (result.ok) { accepted.value = true; acceptedCookbook.value = result.cookbook; }
  else { acceptError.value = result.message; isExpired.value = result.expired === true; }
  isAccepting.value = false;
}

async function openCookbook(): Promise<void> {
  if (acceptedCookbook.value) await router.push({ name: 'cookbook', params: { id: acceptedCookbook.value.id } });
}

onMounted(loadInvitation);
</script>

<template>
  <main class="invitation-card">
    <p class="kicker">Invitation cookbook</p>
    <p v-if="isLoading" class="loading" role="status">Chargement de l’invitation...</p>
    <section v-else-if="errorMessage" class="state" aria-labelledby="invitation-error-title">
      <h2 id="invitation-error-title">{{ isExpired ? 'Invitation expirée' : 'Invitation indisponible' }}</h2>
      <p class="error-summary" role="alert">{{ errorMessage }}</p>
      <RouterLink class="secondary-link" :to="{ name: 'dashboard' }">Retour au tableau de bord</RouterLink>
    </section>
    <section v-else-if="invitation && !accepted" class="state" aria-labelledby="invitation-title">
      <h2 id="invitation-title">Rejoindre « {{ invitation.cookbook.name }} »</h2>
      <p>Cette invitation est proposée à <strong>{{ invitation.email }}</strong>.</p>
      <p>Rôle proposé : <strong>{{ invitation.role === 'editor' ? 'éditeur' : 'lecteur' }}</strong>.</p>
      <p class="expiry">Valable jusqu’au {{ new Date(invitation.expires_at).toLocaleString('fr-FR') }}.</p>
      <RouterLink v-if="!isAuthenticated" class="primary-link" :to="loginTarget">Connectez-vous pour accepter</RouterLink>
      <button v-else type="button" class="primary-button" :disabled="isAccepting" @click="accept">{{ isAccepting ? 'Acceptation...' : 'Accepter l’invitation' }}</button>
      <p v-if="acceptError" class="error-summary" role="alert">{{ acceptError }}</p>
    </section>
    <section v-else class="state" aria-labelledby="accepted-title">
      <h2 id="accepted-title">Invitation acceptée</h2>
      <p>Vous avez rejoint « {{ acceptedCookbook?.name }} ».</p>
      <button type="button" class="primary-button" @click="openCookbook">Ouvrir le cookbook</button>
    </section>
  </main>
</template>

<style scoped>
.invitation-card { max-width: 42rem; margin: 0 auto; padding: 2rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 1.5rem; background: rgba(255, 253, 248, 0.92); box-shadow: 0 20px 60px rgba(54, 68, 35, 0.1); }
.kicker { margin: 0 0 0.35rem; color: #6b7b57; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; }
.state { display: grid; gap: 0.8rem; }
h2 { margin: 0; font-size: clamp(1.8rem, 4vw, 2.6rem); }
.state p { margin: 0; line-height: 1.55; }
.expiry { color: #50634d; }
.error-summary { color: #8f1e1e; }
.primary-button, .primary-link { justify-self: start; padding: 0.7rem 0.9rem; border: 1px solid #395330; border-radius: 0.55rem; background: #395330; color: #fffdf8; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
.primary-button:disabled { cursor: wait; opacity: 0.6; }
.secondary-link { color: #395330; font-weight: 700; }
.loading { margin: 0; }
</style>
