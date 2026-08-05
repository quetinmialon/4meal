<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();
const state = ref<'loading' | 'waiting' | 'success' | 'error'>('loading');
const message = ref('Vérification de votre adresse email en cours…');
const resendMessage = ref('');
const isResending = ref(false);

const isAuthenticatedUnverified = computed(
  () => authStore.isAuthenticated && authStore.user?.email_verified === false,
);

async function verify(): Promise<void> {
  const userId = route.params.id;
  const token = route.params.token;

  if (typeof userId !== 'string' || typeof token !== 'string' || userId === '' || token === '') {
    state.value = 'waiting';
    message.value = 'Consultez votre boîte mail puis utilisez le lien de vérification reçu.';
    return;
  }

  const result = await authStore.verifyEmail(userId, token);
  if (result.ok) {
    state.value = 'success';
    message.value = 'Votre adresse email est maintenant vérifiée. Vous pouvez accéder à votre espace.';
    return;
  }

  state.value = 'error';
  message.value = result.message;
}

async function resend(): Promise<void> {
  isResending.value = true;
  resendMessage.value = '';

  const result = await authStore.resendEmailVerification();
  resendMessage.value = result.message;
  isResending.value = false;
}

onMounted(() => {
  void verify();
});
</script>

<template>
  <main class="verification-card" aria-live="polite">
    <p class="kicker">Vérification email</p>

    <div v-if="state === 'loading'">
      <h2>Vérification en cours…</h2>
      <p class="detail">Nous vérifions votre lien. Veuillez patienter.</p>
    </div>

    <div v-else-if="state === 'waiting'">
      <h2>Vérifiez votre adresse email</h2>
      <p class="detail">{{ message }}</p>
      <button
        v-if="isAuthenticatedUnverified"
        class="primary-button"
        type="button"
        :disabled="isResending"
        @click="resend"
      >
        {{ isResending ? 'Envoi en cours…' : 'Renvoyer l’email' }}
      </button>
      <p v-if="resendMessage" class="feedback" role="status">{{ resendMessage }}</p>
      <RouterLink v-else class="secondary-link" :to="{ name: 'login' }">Se connecter pour renvoyer l’email</RouterLink>
    </div>

    <div v-else-if="state === 'success'">
      <h2>Adresse vérifiée</h2>
      <p class="success-message">{{ message }}</p>
      <RouterLink class="primary-link" :to="{ name: 'login' }">Se connecter</RouterLink>
    </div>

    <div v-else>
      <h2>Vérification impossible</h2>
      <p class="error-message" role="alert">{{ message }}</p>
      <p class="detail">Demandez un nouvel email puis utilisez le dernier lien reçu.</p>

      <button
        v-if="isAuthenticatedUnverified"
        class="primary-button"
        type="button"
        :disabled="isResending"
        @click="resend"
      >
        {{ isResending ? 'Envoi en cours…' : 'Renvoyer l’email' }}
      </button>
      <p v-if="resendMessage" class="feedback" role="status">{{ resendMessage }}</p>
      <RouterLink v-else class="secondary-link" :to="{ name: 'login' }">Se connecter pour renvoyer l’email</RouterLink>
    </div>
  </main>
</template>

<style scoped>
.verification-card {
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
  color: #6b7b57;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

h2 { margin: 0 0 1rem; font-size: clamp(1.9rem, 4vw, 2.8rem); line-height: 1; }
.detail, .success-message, .error-message, .feedback { max-width: 34rem; line-height: 1.6; }
.detail { color: #50634d; }
.success-message { color: #35652f; }
.error-message { color: #8d2727; }
.feedback { color: #50634d; }
.primary-link, .secondary-link, .primary-button { display: inline-flex; margin-top: 1.25rem; padding: 0.9rem 1.25rem; border-radius: 999px; font: inherit; font-weight: 700; text-decoration: none; }
.primary-link, .primary-button { border: 0; background: #2f4520; color: #f7f4ee; cursor: pointer; }
.secondary-link { background: #edf4e6; color: #2f4520; }
.primary-button:disabled { cursor: wait; opacity: 0.7; }
.primary-link:focus-visible, .secondary-link:focus-visible, .primary-button:focus-visible { outline: 3px solid rgba(116, 144, 88, 0.32); outline-offset: 3px; }
</style>
