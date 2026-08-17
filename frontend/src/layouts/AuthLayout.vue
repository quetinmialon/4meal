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
.auth-layout { min-height: 100vh; padding: 2rem 1.5rem 3rem; background: var(--app-background); color: var(--app-text); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; }
.auth-header { max-width: 42rem; margin: 0 auto 2rem; }
.brand { color: inherit; font-size: 1rem; font-weight: 800; letter-spacing: .14em; text-decoration: none; }
.auth-header p { margin: .65rem 0 0; color: var(--app-muted); }
.auth-content { display: flex; width: 100%; min-height: calc(100vh - 8rem); align-items: flex-start; justify-content: center; }
.auth-content :deep(.login-card),
.auth-content :deep(.register-card),
.auth-content :deep(.auth-card),
.auth-content :deep(.two-factor-card),
.auth-content :deep(.verification-card),
.auth-content :deep(.success-card) { width: 100%; max-width: 42rem; margin: 0; }
.auth-content :deep(input:focus-visible),
.auth-content :deep(button:focus-visible),
.auth-content :deep(a:focus-visible) { outline: 3px solid rgba(116, 144, 88, .38); outline-offset: 3px; }
@media (max-width: 40rem) {
  .auth-layout { padding: 1.25rem 1rem 2rem; }
  .auth-header { margin-bottom: 1.25rem; }
  .auth-content { min-height: auto; }
  .auth-content :deep(.login-card),
  .auth-content :deep(.register-card),
  .auth-content :deep(.auth-card),
  .auth-content :deep(.two-factor-card),
  .auth-content :deep(.verification-card),
  .auth-content :deep(.success-card) { padding: 1.35rem; border-radius: 1.15rem; }
}
</style>
