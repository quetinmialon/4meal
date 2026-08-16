<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="auth-layout">
    <header class="auth-header">
      <RouterLink class="brand" :to="{ name: 'login' }">SUPMEAL</RouterLink>
      <p>Votre espace cuisine</p>
    </header>
    <main class="auth-content"><slot /></main>
  </div>
</template>

<style scoped>
.auth-layout { min-height: 100vh; padding: 2rem 1.5rem; background: var(--app-background); color: var(--app-text); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; }
.auth-header { max-width: 48rem; margin: 0 auto 2rem; }
.brand { color: inherit; font-size: .9rem; font-weight: 800; letter-spacing: .14em; text-decoration: none; }
.auth-header p { margin: .65rem 0 0; color: var(--app-muted); }
.auth-content { width: 100%; }
</style>
