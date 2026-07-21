<script setup lang="ts">
const googleAuthEndpoint = import.meta.env.VITE_GOOGLE_AUTH_ENDPOINT || '/api/auth/google/redirect';
</script>

<template>
  <a
    class="google-auth-button"
    :href="googleAuthEndpoint"
    aria-label="Continuer avec Google"
  >
    <span class="google-mark" aria-hidden="true">G</span>
    <span>Continuer avec Google</span>
  </a>
</template>

<style scoped>
.google-auth-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  width: 100%;
  min-height: 3rem;
  padding: 0.75rem 1.25rem;
  border: 1px solid #b4bead;
  border-radius: 999px;
  background: #fffdf9;
  color: #243127;
  font: inherit;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.google-auth-button:hover {
  background: #f5f8f1;
}

.google-auth-button:focus-visible {
  outline: 3px solid rgba(116, 144, 88, 0.32);
  outline-offset: 3px;
}

.google-mark {
  color: #4285f4;
  font-family: Arial, sans-serif;
  font-size: 1.15rem;
  font-weight: 800;
}
</style>
