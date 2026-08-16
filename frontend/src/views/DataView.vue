<template>
  <main class="data-page">
    <p class="kicker">Mes données</p>
    <h1>Import &amp; Export</h1>
    <p class="intro">Gérez les échanges de données de votre espace SUPMEAL.</p>
    <nav class="data-actions" aria-label="Actions sur les données">
      <RouterLink :to="{ name: 'import' }">Importer des données</RouterLink>
      <RouterLink :to="{ name: 'export' }">Exporter mes données</RouterLink>
    </nav>
  </main>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<style scoped>
.data-page { width: 100%; max-width: 76rem; margin: 0 auto; padding: 2rem; box-sizing: border-box; }
.kicker { margin: 0 0 .35rem; color: var(--app-muted); font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1 { margin: 0 0 .75rem; }
.intro { color: var(--app-muted); }
.data-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
.data-actions a { color: var(--app-text); font-weight: 700; }
</style>
