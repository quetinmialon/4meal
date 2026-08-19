<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';

import { pinia } from '@/pinia';
import { useRealtimeStore, type RealtimeToast } from '@/stores/realtime';
import type { AppNotification } from '@/utils/notifications';

const router = useRouter();
const realtimeStore = useRealtimeStore(pinia);
const timers = new Map<string, ReturnType<typeof setTimeout>>();

function targetFor(notification: AppNotification): { name: string; params?: { id: string } } | null {
  if (notification.type === 'cookbook_message') return { name: 'cookbook-messages', params: { id: notification.data.cookbook.id } };
  if (notification.type === 'cookbook_invitation') return { name: 'dashboard' };
  return { name: 'recipe-detail', params: { id: notification.data.recipe.id } };
}

function scheduleDismiss(toasts: RealtimeToast[]): void {
  toasts.forEach((toast) => {
    if (timers.has(toast.id)) return;
    timers.set(toast.id, setTimeout(() => {
      realtimeStore.dismissToast(toast.id);
      timers.delete(toast.id);
    }, 6500));
  });
}

function dismiss(toast: RealtimeToast): void {
  const timer = timers.get(toast.id);
  if (timer !== undefined) clearTimeout(timer);
  timers.delete(toast.id);
  realtimeStore.dismissToast(toast.id);
}

async function open(toast: RealtimeToast): Promise<void> {
  const target = targetFor(toast.notification);
  dismiss(toast);
  if (target) await router.push(target);
}

watch(() => realtimeStore.toasts, scheduleDismiss, { deep: true, immediate: true });

onMounted(() => scheduleDismiss(realtimeStore.toasts));
onBeforeUnmount(() => {
  timers.forEach((timer) => clearTimeout(timer));
  timers.clear();
});
</script>

<template>
  <div class="realtime-toast-stack" aria-live="polite" aria-atomic="false">
    <div v-for="toast in realtimeStore.toasts" :key="toast.id" class="realtime-toast" role="status">
      <div class="realtime-toast-copy"><strong>{{ toast.title }}</strong><span>{{ toast.message }}</span></div>
      <div class="realtime-toast-actions">
        <button type="button" @click="open(toast)">Voir</button>
        <button type="button" aria-label="Fermer la notification" @click="dismiss(toast)">×</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.realtime-toast-stack { position: fixed; z-index: 60; right: 1rem; bottom: 1rem; display: grid; width: min(24rem, calc(100vw - 2rem)); gap: .6rem; }
.realtime-toast { display: flex; align-items: start; justify-content: space-between; gap: .8rem; padding: .8rem 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); box-shadow: 0 .5rem 1.5rem rgb(38 48 42 / 15%); }
.realtime-toast-copy { display: grid; min-width: 0; gap: .2rem; }
.realtime-toast-copy span { overflow: hidden; color: var(--color-text-secondary); font-size: .85rem; text-overflow: ellipsis; white-space: nowrap; }
.realtime-toast-actions { display: flex; flex: 0 0 auto; gap: .35rem; }
.realtime-toast-actions button { border: 0; background: transparent; color: var(--color-primary); font: inherit; font-weight: 700; cursor: pointer; }
.realtime-toast-actions button:last-child { color: var(--color-text-muted); font-size: 1.2rem; line-height: 1; }
</style>
