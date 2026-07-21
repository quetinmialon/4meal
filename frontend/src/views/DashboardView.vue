<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const router = useRouter();

const userName = computed(() => authStore.user?.name ?? '');
const userEmail = computed(() => authStore.user?.email ?? '');

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
