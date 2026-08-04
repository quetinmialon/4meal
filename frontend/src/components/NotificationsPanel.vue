<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import { fetchNotifications, markNotificationAsRead, type AppNotification } from '@/utils/notifications';

const props = defineProps<{
  tokenType: string;
  accessToken: string;
}>();

const router = useRouter();
const notifications = ref<AppNotification[]>([]);
const unreadCount = ref(0);
const isLoading = ref(true);
const errorMessage = ref('');
const markingId = ref<string | null>(null);
let refreshTimer: ReturnType<typeof setInterval> | null = null;

async function loadNotifications(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchNotifications(props.tokenType, props.accessToken);
  if (result.ok) {
    notifications.value = result.notifications;
    unreadCount.value = result.unreadCount;
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

function notificationTitle(notification: AppNotification): string {
  if (notification.type === 'cookbook_message') return `${notification.data.sender.name} a envoyé un message`;
  return notification.type === 'recipe_comment_reply'
    ? `${notification.data.sender.name} a répondu à votre commentaire`
    : `${notification.data.sender.name} a commenté votre recette`;
}

function notificationContext(notification: AppNotification): string {
  return notification.type === 'cookbook_message'
    ? `${notification.data.cookbook.name} · ${notification.data.message.preview}`
    : `${notification.data.recipe.title} · ${notification.data.comment.preview}`;
}

function notificationTarget(notification: AppNotification): { name: string; params: { id: string } } {
  return notification.type === 'cookbook_message'
    ? { name: 'cookbook-messages', params: { id: notification.data.cookbook.id } }
    : { name: 'recipe-detail', params: { id: notification.data.recipe.id } };
}

async function openNotification(notification: AppNotification): Promise<void> {
  if (notification.read_at === null) {
    markingId.value = notification.id;
    const result = await markNotificationAsRead(notification.id, props.tokenType, props.accessToken);
    if (result.ok) {
      notification.read_at = result.notification.read_at;
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    markingId.value = null;
  }

  await router.push(notificationTarget(notification));
}

function refreshOnVisibility(): void {
  if (document.visibilityState === 'visible') void loadNotifications();
}

onMounted(() => {
  void loadNotifications();
  window.addEventListener('focus', refreshOnVisibility);
  document.addEventListener('visibilitychange', refreshOnVisibility);
  refreshTimer = setInterval(() => { void loadNotifications(); }, 30_000);
});

onBeforeUnmount(() => {
  window.removeEventListener('focus', refreshOnVisibility);
  document.removeEventListener('visibilitychange', refreshOnVisibility);
  if (refreshTimer !== null) clearInterval(refreshTimer);
});
</script>

<template>
  <section class="notifications-section" aria-labelledby="notifications-title">
    <div class="section-heading">
      <div>
        <p class="kicker">Activite</p>
        <h3 id="notifications-title">Notifications</h3>
      </div>
      <span v-if="unreadCount > 0" class="notification-count" aria-label="notifications non lues">{{ unreadCount }}</span>
    </div>
    <p v-if="isLoading" class="loading" role="status">Chargement des notifications...</p>
    <p v-else-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
    <p v-else-if="notifications.length === 0" class="empty-state">Aucune notification de message.</p>
    <div v-else class="notification-list">
      <RouterLink
        v-for="notification in notifications"
        :key="notification.id"
        class="notification-item"
        :class="{ unread: notification.read_at === null }"
        :to="notificationTarget(notification)"
        @click.prevent="openNotification(notification)"
      >
        <span>
          <strong>{{ notificationTitle(notification) }}</strong>
          <small>{{ notificationContext(notification) }}</small>
          <time v-if="notification.created_at" :datetime="notification.created_at">{{ new Date(notification.created_at).toLocaleString('fr-FR') }}</time>
        </span>
        <span v-if="notification.read_at === null" class="unread-label">Non lu</span>
        <span v-else-if="markingId === notification.id" class="unread-label">...</span>
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
.notifications-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(86, 112, 79, 0.18); }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.kicker { margin: 0 0 .35rem; color: #6b7b57; font-size: .8rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h3 { margin: 0; font-size: 1.5rem; }
.notification-count { min-width: 1.7rem; padding: .25rem .5rem; border-radius: 999px; background: #e6a84e; color: #fffdf8; text-align: center; font-weight: 700; }
.notification-list { display: grid; gap: .7rem; margin-top: 1rem; }
.notification-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border: 1px solid rgba(86, 112, 79, .2); border-radius: .8rem; color: #243127; text-decoration: none; }
.notification-item.unread { border-color: #6b7b57; background: #f7fbf3; }
.notification-item strong, .notification-item small, .notification-item time { display: block; }
.notification-item small, .notification-item time { margin-top: .25rem; color: #50634d; }
.notification-item time { font-size: .8rem; }
.unread-label { flex: 0 0 auto; color: #395330; font-size: .8rem; font-weight: 700; }
.loading, .error-summary, .empty-state { margin-top: 1rem; }
.error-summary { color: #8f1e1e; }
.empty-state { color: #50634d; }
</style>
