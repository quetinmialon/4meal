<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import CookbookCreateForm from '@/components/CookbookCreateForm.vue';
import { useAuthStore } from '@/stores/auth';
import type { Cookbook, Pagination } from '@/utils/cookbooks';
import { fetchCookbooks } from '@/utils/cookbooks';

const authStore = useAuthStore();
const router = useRouter();

const userName = computed(() => authStore.user?.name ?? '');
const userEmail = computed(() => authStore.user?.email ?? '');
const isCreateFormVisible = ref(false);
const cookbooks = ref<Cookbook[]>([]);
const pagination = ref<Pagination | null>(null);
const isLoading = ref(true);
const errorMessage = ref('');

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

onMounted(() => loadCookbooks());

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
    <section class="cookbooks-section" aria-labelledby="cookbooks-title">
      <div class="section-heading">
        <div>
          <p class="kicker">Mes recettes</p>
          <h3 id="cookbooks-title">Mes cookbooks</h3>
        </div>
        <button class="create-button" type="button" @click="isCreateFormVisible = !isCreateFormVisible">
          {{ isCreateFormVisible ? 'Fermer' : 'Nouveau cookbook' }}
        </button>
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

.cookbooks-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.section-heading .kicker { margin-bottom: 0.35rem; }
h3 { margin: 0; font-size: 1.5rem; }
.create-button { padding: 0.7rem 0.9rem; border: 1px solid #395330; border-radius: 0.65rem; background: transparent; color: #395330; font: inherit; font-weight: 700; cursor: pointer; }
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
