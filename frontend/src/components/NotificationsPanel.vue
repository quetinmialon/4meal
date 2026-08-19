<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Bell } from '@lucide/vue';

import { fetchNotifications, markAllNotificationsAsRead, markNotificationAsRead, type AppNotification } from '@/utils/notifications';
import { pinia } from '@/pinia';
import { useRealtimeStore } from '@/stores/realtime';

const props = defineProps<{
  tokenType: string;
  accessToken: string;
  compact?: boolean;
}>();

const router = useRouter();
const realtimeStore = useRealtimeStore(pinia);
const notifications = computed(() => realtimeStore.notifications);
const unreadCount = computed(() => realtimeStore.unreadCount);
const isLoading = ref(true);
const errorMessage = ref('');
const markingId = ref<string | null>(null);
const isPopoverOpen = ref(false);
const compactRoot = ref<HTMLElement | null>(null);
let refreshTimer: ReturnType<typeof setInterval> | null = null;

async function loadNotifications(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = '';
  const result = await fetchNotifications(props.tokenType, props.accessToken);
  if (result.ok) {
    realtimeStore.setNotifications(result.notifications, result.unreadCount);
  } else {
    errorMessage.value = result.message;
  }
  isLoading.value = false;
}

function notificationTitle(notification: AppNotification): string {
  if (notification.type === 'cookbook_invitation') return notification.data.status === 'pending' ? 'Nouvelle invitation à un cookbook' : `Invitation ${notification.data.status === 'accepted' ? 'acceptée' : 'refusée'}`;
  if (notification.type === 'cookbook_message') return `${notification.data.sender.name} a envoyé un message`;
  return notification.type === 'recipe_comment_reply'
    ? `${notification.data.sender.name} a répondu à votre commentaire`
    : `${notification.data.sender.name} a commenté votre recette`;
}

function notificationContext(notification: AppNotification): string {
  if (notification.type === 'cookbook_invitation') return notification.data.invitation.cookbook.name;
  return notification.type === 'cookbook_message'
    ? `${notification.data.cookbook.name} · ${notification.data.message.preview}`
    : `${notification.data.recipe.title} · ${notification.data.comment.preview}`;
}

function notificationTypeLabel(notification: AppNotification): string {
  if (notification.type === 'cookbook_invitation') return 'Invitation cookbook';
  if (notification.type === 'cookbook_message') return 'Message de cookbook';
  return notification.type === 'recipe_comment_reply' ? 'Réponse à un commentaire' : 'Commentaire sur une recette';
}

function notificationAge(value: string | null): string {
  if (!value) return 'Date inconnue';
  const date = new Date(value);
  const seconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));
  if (seconds < 60) return 'À l’instant';
  if (seconds < 3600) return `Il y a ${Math.floor(seconds / 60)} min`;
  if (seconds < 86400) return `Il y a ${Math.floor(seconds / 3600)} h`;
  if (seconds < 604800) return `Il y a ${Math.floor(seconds / 86400)} j`;
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function togglePopover(): void { isPopoverOpen.value = !isPopoverOpen.value; }
function closePopover(): void { isPopoverOpen.value = false; }
function handlePointerDown(event: PointerEvent): void {
  if (props.compact && isPopoverOpen.value && !compactRoot.value?.contains(event.target as Node)) closePopover();
}
function handleKeydown(event: KeyboardEvent): void {
  if (props.compact && event.key === 'Escape' && isPopoverOpen.value) closePopover();
}

function notificationTarget(notification: AppNotification): { name: string; params?: { id: string } } {
  if (notification.type === 'cookbook_invitation') return { name: 'dashboard' };
  return notification.type === 'cookbook_message'
    ? { name: 'cookbook-messages', params: { id: notification.data.cookbook.id } }
    : { name: 'recipe-detail', params: { id: notification.data.recipe.id } };
}

async function openNotification(notification: AppNotification): Promise<void> {
  if (notification.read_at === null) {
    markingId.value = notification.id;
    const result = await markNotificationAsRead(notification.id, props.tokenType, props.accessToken);
    if (result.ok) {
      realtimeStore.upsertNotification({ ...notification, read_at: result.notification.read_at });
    }
    markingId.value = null;
  }

  closePopover();
  await router.push(notificationTarget(notification));
}

async function markAllAsRead(): Promise<void> {
  if (unreadCount.value === 0) return;
  markingId.value = 'all';
  const result = await markAllNotificationsAsRead(props.tokenType, props.accessToken);
  if (result.ok) {
    realtimeStore.setNotifications(
      notifications.value.map((notification) => ({ ...notification, read_at: notification.read_at ?? result.readAt })),
      0,
    );
  } else {
    errorMessage.value = result.message;
  }
  markingId.value = null;
}

function refreshOnVisibility(): void {
  if (document.visibilityState === 'visible') void loadNotifications();
}

onMounted(() => {
  void loadNotifications();
  window.addEventListener('focus', refreshOnVisibility);
  document.addEventListener('visibilitychange', refreshOnVisibility);
  document.addEventListener('pointerdown', handlePointerDown);
  document.addEventListener('keydown', handleKeydown);
  refreshTimer = setInterval(() => { void loadNotifications(); }, 30_000);
});

onBeforeUnmount(() => {
  window.removeEventListener('focus', refreshOnVisibility);
  document.removeEventListener('visibilitychange', refreshOnVisibility);
  document.removeEventListener('pointerdown', handlePointerDown);
  document.removeEventListener('keydown', handleKeydown);
  if (refreshTimer !== null) clearInterval(refreshTimer);
});
</script>

<template>
  <div v-if="compact" ref="compactRoot" class="notification-popover">
    <button type="button" class="notification-trigger" aria-label="Ouvrir les notifications" :aria-expanded="isPopoverOpen" aria-controls="notifications-popover" @click="togglePopover">
      <Bell :size="20" aria-hidden="true" />
      <span v-if="unreadCount > 0" class="notification-badge" aria-label="notifications non lues">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>
    <div v-if="isPopoverOpen" id="notifications-popover" class="notification-popover-panel" role="dialog" aria-labelledby="notifications-popover-title">
      <button v-if="unreadCount > 0" type="button" class="mark-all-button" :disabled="markingId === 'all'" @click="markAllAsRead">Tout lire</button>
      <div class="popover-heading"><div><p class="kicker">Activité</p><h3 id="notifications-popover-title">Notifications</h3></div><span v-if="unreadCount > 0" class="popover-unread-count">{{ unreadCount }} non lue<span v-if="unreadCount > 1">s</span></span></div>
      <p v-if="isLoading" class="loading" role="status">Chargement des notifications...</p>
      <p v-else-if="errorMessage" class="error-summary" role="alert">{{ errorMessage }}</p>
      <p v-else-if="notifications.length === 0" class="empty-state">Aucune notification récente.</p>
      <div v-else class="notification-list">
        <RouterLink v-for="notification in notifications" :key="notification.id" class="notification-item" :class="{ unread: notification.read_at === null }" :to="notificationTarget(notification)" @click.prevent="openNotification(notification)">
          <span class="notification-copy"><strong>{{ notificationTitle(notification) }}</strong><small class="notification-type">{{ notificationTypeLabel(notification) }}</small><small>{{ notificationContext(notification) }}</small><time v-if="notification.created_at" :datetime="notification.created_at" :title="new Date(notification.created_at).toLocaleString('fr-FR')">{{ notificationAge(notification.created_at) }}</time></span>
          <span v-if="notification.read_at === null" class="unread-label">Non lue</span>
        </RouterLink>
      </div>
    </div>
  </div>
  <section v-else class="notifications-section" aria-labelledby="notifications-title">
    <div class="section-heading">
      <button v-if="unreadCount > 0" type="button" class="mark-all-button" :disabled="markingId === 'all'" @click="markAllAsRead">Tout marquer comme lu</button>
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
          <small class="notification-type">{{ notificationTypeLabel(notification) }}</small>
          <small>{{ notificationContext(notification) }}</small>
          <time v-if="notification.created_at" :datetime="notification.created_at" :title="new Date(notification.created_at).toLocaleString('fr-FR')">{{ notificationAge(notification.created_at) }}</time>
        </span>
        <span v-if="notification.read_at === null" class="unread-label">Non lu</span>
        <span v-else-if="markingId === notification.id" class="unread-label">...</span>
      </RouterLink>
    </div>
  </section>
</template>

<style scoped>
.notification-popover { position: relative; }
.notification-trigger { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; min-height: 2.5rem; border: 1px solid var(--app-border, rgba(86,112,79,.18)); border-radius: .65rem; background: transparent; color: var(--app-text, #243127); cursor: pointer; }
.notification-badge { position: absolute; top: -.35rem; right: -.4rem; min-width: 1.15rem; padding: .12rem .28rem; border: 2px solid var(--app-surface, #fffdf8); border-radius: 999px; background: #b5483b; color: #fffdf8; font-size: .68rem; font-weight: 800; line-height: 1.2; }
.notification-popover-panel { position: absolute; z-index: 20; top: calc(100% + .6rem); right: 0; width: min(24rem, calc(100vw - 2rem)); max-height: min(34rem, calc(100vh - 6rem)); overflow: auto; padding: 1rem; border: 1px solid var(--app-border, rgba(86,112,79,.18)); border-radius: .85rem; background: var(--app-surface, #fffdf8); box-shadow: 0 16px 38px rgba(36,49,39,.2); }
.popover-heading { display: flex; align-items: start; justify-content: space-between; gap: .75rem; padding-bottom: .8rem; border-bottom: 1px solid var(--app-border, rgba(86,112,79,.18)); }
.popover-heading h3 { margin: 0; font-size: 1.1rem; }.popover-unread-count { color: #395330; font-size: .8rem; font-weight: 700; }
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
.notification-copy { min-width: 0; }.notification-copy strong, .notification-copy small { overflow: hidden; text-overflow: ellipsis; }.notification-type { color: #395330 !important; font-size: .78rem; font-weight: 700; }.notification-copy time { font-size: .8rem; }
.notification-item time { font-size: .8rem; }
.mark-all-button { padding: .35rem .55rem; border: 1px solid var(--app-border, rgba(86,112,79,.3)); border-radius: .45rem; background: transparent; color: var(--app-text, #243127); font: inherit; font-size: .78rem; font-weight: 700; cursor: pointer; }
.mark-all-button:hover, .mark-all-button:focus-visible { background: var(--app-surface-subtle, #edf4e8); }
.mark-all-button:disabled { cursor: wait; opacity: .55; }
.unread-label { flex: 0 0 auto; color: #395330; font-size: .8rem; font-weight: 700; }
.loading, .error-summary, .empty-state { margin-top: 1rem; }
.error-summary { color: #8f1e1e; }
.empty-state { color: #50634d; }
@media (max-width: 52rem) { .notification-popover-panel { position: fixed; top: 4.1rem; right: .75rem; } }
</style>
