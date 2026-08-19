<script setup lang="ts">
import { computed, watch } from 'vue';
import { RouterView, useRoute } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import RealtimeToastStack from '@/components/RealtimeToastStack.vue';
import { useAuthStore } from '@/stores/auth';
import { useRealtimeStore } from '@/stores/realtime';

const route = useRoute();
const authStore = useAuthStore();
const realtimeStore = useRealtimeStore();
const layout = computed(() => (route.meta.requiresAuth ? AppLayout : AuthLayout));
const cookbookId = computed(() => String(route.params.id ?? ''));
const cookbookRouteNames = new Set(['cookbook', 'cookbook-recipes', 'cookbook-members', 'cookbook-planning', 'cookbook-settings', 'cookbook-messages']);
const currentCookbookId = computed(() => cookbookRouteNames.has(String(route.name)) ? cookbookId.value : '');

watch(
  () => [authStore.user?.id ?? null, authStore.tokenType, authStore.accessToken] as const,
  ([userId, tokenType, accessToken]) => {
    if (userId === null || !authStore.isAuthenticated) {
      realtimeStore.disconnect();
      return;
    }
    realtimeStore.connect(authStore.user, tokenType, accessToken);
    if (currentCookbookId.value !== '') realtimeStore.subscribeCookbook(currentCookbookId.value);
  },
  { immediate: true },
);

watch(currentCookbookId, (id) => {
  if (id !== '') realtimeStore.subscribeCookbook(id);
  else realtimeStore.unsubscribeCookbook();
}, { immediate: true });
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
  <RealtimeToastStack />
</template>
