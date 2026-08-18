<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <main class="not-found-page">
    <section class="not-found-card" aria-labelledby="not-found-title">
      <img class="not-found-hero" src="@/assets/errornotfound.png" alt="Illustration d'une cuisine avec une tomate contrariée" />
      <div class="not-found-content">
        <p class="kicker">Erreur 404</p>
        <h1 id="not-found-title">Cette page est introuvable</h1>
        <p>La page demandée n’existe pas ou n’est plus disponible.</p>
        <div class="not-found-actions">
          <RouterLink class="primary-link" :to="{ name: 'dashboard' }">Retour à l’accueil</RouterLink>
          <RouterLink class="secondary-link" :to="{ name: 'login' }">Se connecter</RouterLink>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.not-found-page { width: 100%; max-width: 58rem; margin: 2rem auto; padding: 1rem; box-sizing: border-box; }
.not-found-card { overflow: hidden; border: 1px solid rgba(86,112,79,.18); border-radius: 1.5rem; background: rgba(255,253,248,.94); box-shadow: 0 20px 60px rgba(54,68,35,.1); }
.not-found-hero { display: block; width: 100%; height: clamp(14rem, 30vw, 22rem); object-fit: cover; object-position: center; }
.not-found-content { padding: 1.75rem; text-align: center; }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1 { margin: 0; font-size: clamp(1.9rem, 4vw, 2.8rem); }
.not-found-content > p:not(.kicker) { color: #50634d; line-height: 1.6; }
.not-found-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .7rem; margin-top: 1.5rem; }
.primary-link, .secondary-link { display: inline-flex; align-items: center; justify-content: center; min-height: 2.7rem; padding: .65rem 1rem; border-radius: .85rem; font-weight: 700; text-decoration: none; }
.primary-link { background: #395330; color: #fffdf8; }
.secondary-link { border: 1px solid #395330; color: #395330; }
.primary-link:focus-visible, .secondary-link:focus-visible { outline: 3px solid rgba(116,144,88,.38); outline-offset: 3px; }
@media (max-width: 40rem) { .not-found-page { margin: 1rem auto; padding: .6rem; }.not-found-content { padding: 1.25rem; }.not-found-actions > * { flex: 1 1 100%; } }
</style>
