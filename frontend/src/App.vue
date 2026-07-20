<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-shell">
    <header class="hero">
      <p class="eyebrow">SUPMEAL</p>
      <h1>Retrouver votre espace cuisine</h1>
      <p class="lede">
        Connectez-vous simplement pour retrouver votre espace personnel et poursuivre en toute
        tranquillite.
      </p>
    </header>

    <RouterView />
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  padding: 3rem 1.5rem;
  background:
    radial-gradient(circle at top, #f2f7ea 0, #f2f7ea 18%, transparent 18%),
    linear-gradient(180deg, #f8f5ee 0%, #efe7d7 100%);
  color: #243127;
  font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif;
}

.hero {
  margin: 0 auto 2rem;
  max-width: 48rem;
}

.eyebrow {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #56704f;
}

h1 {
  margin: 0 0 0.75rem;
  font-size: clamp(2.5rem, 7vw, 4.5rem);
  line-height: 0.95;
}

.lede {
  max-width: 36rem;
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.6;
  color: #4b5e49;
}
</style>
