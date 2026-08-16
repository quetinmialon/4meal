<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const route = useRoute();
const layout = computed(() => (route.meta.requiresAuth ? AppLayout : AuthLayout));
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
</template>
