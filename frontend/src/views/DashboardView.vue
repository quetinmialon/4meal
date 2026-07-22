<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import CookbookCreateForm from '@/components/CookbookCreateForm.vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const router = useRouter();

const userName = computed(() => authStore.user?.name ?? '');
const userEmail = computed(() => authStore.user?.email ?? '');
const isCreateFormVisible = ref(false);

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
      <div v-if="!isCreateFormVisible" class="empty-state">
        <p>Vous n’avez encore aucun cookbook.</p>
        <p>Créez votre premier espace pour organiser vos recettes.</p>
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
