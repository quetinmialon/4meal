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

<style>
.auth-layout { min-height: 100vh; padding: 2rem 1.5rem 3rem; background: var(--app-background); color: var(--app-text); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; }
.auth-header { max-width: 42rem; margin: 0 auto 2rem; }
.brand { color: inherit; font-size: 1rem; font-weight: 800; letter-spacing: .14em; text-decoration: none; }
.auth-header p { margin: .65rem 0 0; color: var(--app-muted); }
.auth-content { display: flex; width: 100%; min-height: calc(100vh - 8rem); align-items: flex-start; justify-content: center; }
.auth-screen { display: grid; flex: 0 1 76rem; width: 100%; min-width: 0; grid-template-columns: minmax(0, 2fr) minmax(20rem, 1fr); grid-template-areas: 'illustration form'; align-items: stretch; gap: 0; overflow: hidden; border: 1px solid rgba(86, 112, 79, .18); border-radius: 1.5rem; background: rgba(255, 253, 248, .94); box-shadow: 0 20px 60px rgba(54, 68, 35, .1); }
.auth-illustration { display: block; grid-area: illustration; width: 100%; height: 100%; min-height: 28rem; object-fit: cover; object-position: center; }
.auth-screen .login-card,
.auth-screen .register-card,
.auth-screen .auth-card { grid-area: form; box-sizing: border-box; width: 100%; max-width: none; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
.auth-content .login-card,
.auth-content .register-card,
.auth-content .auth-card,
.auth-content .two-factor-card,
.auth-content .verification-card,
.auth-content .success-card { box-sizing: border-box; width: min(100%, 42rem); min-width: 0; max-width: 42rem; margin: 0; }
.auth-content form,
.auth-content fieldset,
.auth-content .field,
.auth-content input,
.auth-content button { box-sizing: border-box; min-width: 0; }
.auth-content form { width: 100%; }
.auth-content input { max-width: 100%; }
.auth-content .oauth-separator { margin-bottom: .75rem; }
.auth-content input:focus-visible,
.auth-content button:focus-visible,
.auth-content a:focus-visible { outline: 3px solid rgba(116, 144, 88, .38); outline-offset: 3px; }
@media (max-width: 52rem) {
  .auth-layout { padding: 1.25rem 1rem 2rem; }
  .auth-header { margin-bottom: 1.25rem; }
  .auth-content { min-height: auto; }
  .auth-screen { grid-template-columns: 1fr; grid-template-areas: 'illustration' 'form'; }
  .auth-screen .login-card,
  .auth-screen .register-card,
  .auth-screen .auth-card { grid-area: form; }
  .auth-illustration { grid-area: illustration; min-height: 10rem; max-height: 13rem; }
  .auth-content .login-card,
  .auth-content .register-card,
  .auth-content .auth-card,
  .auth-content .two-factor-card,
  .auth-content .verification-card,
  .auth-content .success-card { padding: 1.35rem; border-radius: 1.15rem; }
}
</style>
