<script setup lang="ts">
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="app-shell">
    <header class="hero">
      <p class="eyebrow">Vue 3 scaffold</p>
      <h1>Frontend ready</h1>
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
  margin: 0;
  font-size: clamp(2.5rem, 7vw, 4.5rem);
  line-height: 0.95;
}
</style>
