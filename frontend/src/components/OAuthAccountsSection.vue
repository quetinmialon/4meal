<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { useAuthStore } from '@/stores/auth';
import {
  fetchOAuthAccounts,
  oauthProviderLabel,
  startOAuthLink,
  unlinkOAuthAccount,
  type OAuthAccount,
  type OAuthProvider,
} from '@/utils/oauthAccounts';

const authStore = useAuthStore();
const accounts = ref<OAuthAccount[]>([]);
const isOpen = ref(false);
const isLoading = ref(false);
const loadingError = ref('');
const actionError = ref('');
const successMessage = ref('');
const deletingProvider = ref<OAuthProvider | null>(null);
const linkingProvider = ref<OAuthProvider | null>(null);

const linkedProviders = computed(() => new Set(accounts.value.map((account) => account.provider)));
const availableProviders = computed(() => (['google', 'microsoft'] as OAuthProvider[]).filter((provider) => !linkedProviders.value.has(provider)));

async function loadAccounts(): Promise<void> {
  if (authStore.accessToken === '' || authStore.tokenType === '') {
    loadingError.value = 'Une authentification est requise.';
    return;
  }

  isLoading.value = true;
  loadingError.value = '';
  const result = await fetchOAuthAccounts(authStore.tokenType, authStore.accessToken);
  if (result.ok) accounts.value = result.accounts;
  else loadingError.value = result.message;
  isLoading.value = false;
}

async function removeProvider(account: OAuthAccount): Promise<void> {
  if (!window.confirm(`Supprimer le compte ${oauthProviderLabel(account.provider)} associé ?`)) return;

  deletingProvider.value = account.provider;
  actionError.value = '';
  successMessage.value = '';
  const result = await unlinkOAuthAccount(account.provider, authStore.tokenType, authStore.accessToken);
  if (result.ok) {
    accounts.value = accounts.value.filter((item) => item.provider !== account.provider);
    successMessage.value = `Le compte ${oauthProviderLabel(account.provider)} a été dissocié.`;
  } else actionError.value = result.message;
  deletingProvider.value = null;
}

async function linkProvider(provider: OAuthProvider): Promise<void> {
  linkingProvider.value = provider;
  actionError.value = '';
  const result = await startOAuthLink(provider, authStore.tokenType, authStore.accessToken);
  if (!result.ok) actionError.value = result.message;
  linkingProvider.value = null;
}

function toggleSection(): void {
  isOpen.value = !isOpen.value;
  if (isOpen.value && accounts.value.length === 0 && loadingError.value === '') void loadAccounts();
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('oauth_linked') !== null) {
    isOpen.value = true;
    successMessage.value = `Le compte ${oauthProviderLabel(params.get('oauth_linked') as OAuthProvider)} a été associé.`;
    void loadAccounts();
  }
});
</script>

<template>
  <details class="oauth-section" :open="isOpen">
    <summary @click.prevent="toggleSection">Comptes de connexion associés</summary>
    <div class="oauth-content">
      <p class="oauth-warning" role="note">
        Conservez toujours au moins un moyen de connexion. La suppression du dernier compte associé sera refusée.
      </p>
      <p class="section-help">Associez Google ou Microsoft pour vous connecter sans saisir votre mot de passe.</p>

      <p v-if="successMessage" class="success-message" role="status" aria-live="polite">{{ successMessage }}</p>
      <p v-if="actionError" class="error-summary" role="alert" aria-live="assertive">{{ actionError }}</p>
      <p v-if="loadingError" class="error-summary" role="alert" aria-live="assertive">
        {{ loadingError }}
        <button type="button" class="inline-button" @click="loadAccounts">Réessayer</button>
      </p>
      <p v-if="isLoading" class="section-help" role="status">Chargement des fournisseurs associés…</p>

      <ul v-else-if="accounts.length > 0" class="oauth-list" aria-label="Fournisseurs associés">
        <li v-for="account in accounts" :key="account.id" class="oauth-account">
          <div>
            <strong>{{ oauthProviderLabel(account.provider) }}</strong>
            <span>{{ account.email }}</span>
          </div>
          <button
            type="button"
            class="danger-button"
            :disabled="deletingProvider === account.provider"
            @click="removeProvider(account)"
          >
            {{ deletingProvider === account.provider ? 'Suppression…' : 'Supprimer' }}
          </button>
        </li>
      </ul>
      <p v-else-if="!loadingError" class="section-help">Aucun fournisseur OAuth n’est actuellement associé.</p>

      <div class="link-actions" aria-label="Associer un fournisseur">
        <button v-for="provider in availableProviders" :key="provider" type="button" class="secondary-button" :disabled="linkingProvider !== null" @click="linkProvider(provider)">
          {{ linkingProvider === provider ? 'Redirection…' : `Associer ${oauthProviderLabel(provider)}` }}
        </button>
      </div>
    </div>
  </details>
</template>

<style scoped>
.oauth-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
summary { cursor: pointer; color: #2f4520; font-weight: 700; }
.oauth-content { display: grid; gap: 1rem; padding-top: 1rem; }
.oauth-warning { margin: 0; padding: 0.85rem 1rem; border: 1px solid #e1c982; border-radius: 0.9rem; background: #fff9e7; color: #6c5310; line-height: 1.5; }
.section-help { margin: 0; color: #50634d; line-height: 1.5; }
.oauth-list { display: grid; gap: 0.7rem; margin: 0; padding: 0; list-style: none; }
.oauth-account { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.9rem 1rem; border: 1px solid rgba(86, 112, 79, 0.18); border-radius: 0.9rem; background: #fffdfa; }
.oauth-account div { display: grid; gap: 0.2rem; }
.oauth-account span { color: #50634d; font-size: 0.92rem; }
.link-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }
.secondary-button, .danger-button, .inline-button { margin: 0; padding: 0.7rem 0.95rem; border-radius: 999px; font: inherit; font-weight: 700; cursor: pointer; }
.secondary-button { border: 1px solid #6b875f; background: #edf4e6; color: #2f4520; }
.danger-button { border: 1px solid #b64242; background: #fff4f2; color: #8f1e1e; }
.inline-button { margin-left: 0.5rem; padding: 0.25rem 0.55rem; border: 1px solid #8f1e1e; background: transparent; color: #8f1e1e; }
.error-summary, .success-message { margin: 0; padding: 0.85rem 1rem; border-radius: 0.9rem; line-height: 1.5; }
.error-summary { border: 1px solid rgba(171, 44, 44, 0.24); background: #fff4f2; color: #8f1e1e; }
.success-message { border: 1px solid #bdd0af; background: #edf4e6; color: #2f4520; }
button:disabled { cursor: wait; opacity: 0.7; }
</style>
