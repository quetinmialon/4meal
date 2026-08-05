<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute();

const registeredEmail = computed(() => {
  const email = route.query.email;

  return typeof email === 'string' ? email : '';
});
</script>

<template>
  <main class="success-card">
    <p class="kicker">Compte créé</p>
    <h2>Vérifiez votre adresse email</h2>
    <p class="message">
      Votre compte a bien été créé<span v-if="registeredEmail"> pour {{ registeredEmail }}</span>.
    </p>
    <p class="detail">
      Un email de vérification vient d’être envoyé. Connectez-vous après l’avoir confirmé pour accéder à votre espace.
    </p>
    <RouterLink class="back-link" :to="{ name: 'login' }">Aller à la connexion</RouterLink>
  </main>
</template>

<style scoped>
.success-card {
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

.back-link {
  display: inline-flex;
  margin-top: 1.5rem;
  padding: 0.9rem 1.25rem;
  border-radius: 999px;
  background: #edf4e6;
  color: #2f4520;
  font-weight: 700;
  text-decoration: none;
}

.back-link:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 3px;
}
</style>
