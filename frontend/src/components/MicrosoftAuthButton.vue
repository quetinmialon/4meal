<script setup lang="ts">
import OAuthButton from './OAuthButton.vue';

const microsoftAuthEndpoint = import.meta.env.VITE_MICROSOFT_AUTH_ENDPOINT || '/api/auth/microsoft/redirect';
</script>

<template>
  <OAuthButton provider="microsoft" :endpoint="microsoftAuthEndpoint" label="Continuer avec Microsoft" />
</template>
