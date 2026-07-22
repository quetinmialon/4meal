<script setup lang="ts">
import OAuthButton from './OAuthButton.vue';

const googleAuthEndpoint = import.meta.env.VITE_GOOGLE_AUTH_ENDPOINT || '/api/auth/google/redirect';
</script>

<template>
  <OAuthButton provider="google" :endpoint="googleAuthEndpoint" label="Continuer avec Google" />
</template>
