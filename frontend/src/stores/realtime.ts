import { defineStore } from 'pinia';
import type { Channel } from 'laravel-echo';

import { createRealtimeConnection, type RealtimeConnection } from '@/realtime/echo';
import type { AuthUser } from '@/stores/auth';
import type { CookbookMessage } from '@/utils/cookbooks';
import type { RecipeComment } from '@/utils/recipes';
import type { AppNotification, CookbookInvitationNotification } from '@/utils/notifications';

type RealtimeStatus = 'disabled' | 'connecting' | 'connected' | 'reconnecting' | 'error';
export type RealtimeToast = {
  id: string;
  title: string;
  message: string;
  notification: AppNotification;
};

type MessageCreatedPayload = { message: CookbookMessage };
type CommentPayload = { recipe: { id: string }; comment: RecipeComment };
type CommentDeletedPayload = { recipe: { id: string }; comment: { id: string } };
type NotificationEvent = {
  id?: string;
  type?: string;
  data?: Record<string, unknown>;
  created_at?: string | null;
};

let echo: RealtimeConnection | null = null;
let userChannel: Channel | null = null;
let cookbookChannel: Channel | null = null;
let subscribedCookbookId: string | null = null;
const displayedNotificationIds = new Set<string>();

function isMessagePayload(payload: unknown): payload is MessageCreatedPayload {
  return typeof payload === 'object' && payload !== null && 'message' in payload;
}

function isCommentPayload(payload: unknown): payload is CommentPayload {
  return typeof payload === 'object' && payload !== null && 'recipe' in payload && 'comment' in payload;
}

function isDeletedPayload(payload: unknown): payload is CommentDeletedPayload {
  return isCommentPayload(payload) && typeof payload.comment === 'object' && payload.comment !== null && 'id' in payload.comment;
}

function isAppNotification(value: unknown): value is AppNotification['data'] {
  if (typeof value !== 'object' || value === null || typeof (value as { type?: unknown }).type !== 'string') return false;
  const type = (value as { type: string }).type;
  return type === 'cookbook_message'
    || type === 'recipe_comment'
    || type === 'recipe_comment_reply'
    || type === 'cookbook_invitation';
}

function notificationFromBroadcast(event: NotificationEvent): AppNotification | null {
  const data = event.data;
  if (!data || !isAppNotification(data) || typeof event.id !== 'string') return null;
  return {
    type: data.type,
    data,
    id: event.id,
    read_at: null,
    created_at: event.created_at ?? new Date().toISOString(),
  } as unknown as AppNotification;
}

function realtimeDebug(message: string, details?: unknown): void {
  if (import.meta.env.DEV) console.debug(`[SUPMEAL realtime] ${message}`, details ?? '');
}

export const useRealtimeStore = defineStore('realtime', {
  state: () => ({
    status: 'disabled' as RealtimeStatus,
    error: null as string | null,
    messagesByCookbook: {} as Record<string, CookbookMessage[]>,
    commentsByRecipe: {} as Record<string, RecipeComment[]>,
    notifications: [] as AppNotification[],
    unreadCount: 0,
    toasts: [] as RealtimeToast[],
    currentUserId: null as number | null,
  }),

  actions: {
    connect(user: AuthUser | null, tokenType: string, accessToken: string): void {
      this.disconnect();
      if (user === null) return;
      this.currentUserId = user.id;
      realtimeDebug('initialisation', { userId: user.id, hasToken: accessToken !== '' });

      echo = createRealtimeConnection({ tokenType, accessToken });
      if (echo === null) {
        realtimeDebug('Echo désactivé ou clé Reverb absente');
        this.status = 'disabled';
        return;
      }

      this.status = 'connecting';
      this.error = null;
      const connection = (echo.connector as { pusher?: { connection?: { bind: (event: string, callback: () => void) => void } } }).pusher?.connection;
      connection?.bind('connected', () => { this.status = 'connected'; this.error = null; realtimeDebug('connexion établie'); });
      connection?.bind('connecting', () => { this.status = 'connecting'; });
      connection?.bind('reconnecting', () => { this.status = 'reconnecting'; });
      connection?.bind('error', () => { this.status = 'error'; this.error = 'Connexion realtime indisponible.'; realtimeDebug('erreur de connexion'); });
      connection?.bind('failed', () => { this.status = 'error'; this.error = 'Connexion realtime indisponible.'; realtimeDebug('connexion échouée'); });

      this.subscribeUser(user.id);
    },

    subscribeUser(userId: number): void {
      if (echo === null) return;
      realtimeDebug(`abonnement user.${userId}`);
      userChannel = echo.private(`user.${userId}`);
      userChannel
        .notification((event: unknown) => { realtimeDebug('notification reçue', event); this.receiveNotification(event); })
        .listen('.cookbook.invitation.created', (event: unknown) => this.receiveInvitation(event))
        .listen('.cookbook.invitation.accepted', (event: unknown) => this.receiveInvitation(event))
        .listen('.cookbook.invitation.declined', (event: unknown) => this.receiveInvitation(event))
        .subscribed(() => { realtimeDebug(`channel user.${userId} prêt`); if (this.status !== 'connected') this.status = 'connected'; })
        .error((event: unknown) => { this.status = 'error'; this.error = 'Abonnement aux notifications impossible.'; realtimeDebug('erreur channel utilisateur', event); });
    },

    subscribeCookbook(cookbookId: string): void {
      if (echo === null || cookbookId === '' || subscribedCookbookId === cookbookId) return;
      this.unsubscribeCookbook();
      cookbookChannel = echo.private(`cookbook.${cookbookId}`);
      subscribedCookbookId = cookbookId;
      realtimeDebug(`abonnement cookbook.${cookbookId}`);
      cookbookChannel
        .listen('.cookbook.message.created', (event: unknown) => {
          realtimeDebug('message reçu', event);
          if (isMessagePayload(event)) this.upsertMessage(cookbookId, event.message);
        })
        .listen('.recipe.comment.created', (event: unknown) => { realtimeDebug('commentaire reçu', event); this.receiveComment(event); })
        .listen('.recipe.comment.updated', (event: unknown) => { realtimeDebug('commentaire modifié reçu', event); this.receiveComment(event); })
        .listen('.recipe.comment.deleted', (event: unknown) => { realtimeDebug('commentaire supprimé reçu', event); this.removeComment(event); })
        .subscribed(() => { realtimeDebug(`channel cookbook.${cookbookId} prêt`); if (this.status !== 'connected') this.status = 'connected'; })
        .error((event: unknown) => { this.status = 'error'; this.error = 'Abonnement au cookbook impossible.'; realtimeDebug('erreur channel cookbook', event); });
    },

    unsubscribeCookbook(cookbookId?: string): void {
      if (cookbookId !== undefined && subscribedCookbookId !== cookbookId) return;
      if (echo !== null && subscribedCookbookId !== null) echo.leave(`cookbook.${subscribedCookbookId}`);
      cookbookChannel = null;
      subscribedCookbookId = null;
    },

    disconnect(): void {
      this.unsubscribeCookbook();
      if (echo !== null) {
        if (userChannel !== null) echo.leaveAllChannels();
        echo.disconnect();
      }
      echo = null;
      userChannel = null;
      this.status = 'disabled';
      this.error = null;
      this.toasts = [];
      this.currentUserId = null;
    },

    receiveComment(payload: unknown): void {
      if (!isCommentPayload(payload) || !isAppComment(payload.comment)) return;
      const recipeId = payload.recipe.id;
      const comments = this.commentsByRecipe[recipeId] ?? [];
      const index = comments.findIndex((comment) => comment.id === payload.comment.id);
      this.commentsByRecipe[recipeId] = index === -1
        ? [...comments, payload.comment]
        : comments.map((comment, itemIndex) => itemIndex === index ? payload.comment : comment);
    },

    removeComment(payload: unknown): void {
      if (!isDeletedPayload(payload)) return;
      const recipeId = payload.recipe.id;
      this.commentsByRecipe[recipeId] = (this.commentsByRecipe[recipeId] ?? []).filter((comment) => comment.id !== payload.comment.id);
    },

    receiveInvitation(payload: unknown): void {
      const invitation = payload as { invitation?: unknown };
      if (invitation.invitation) {
        this.upsertNotification({
          id: `invitation-${Date.now()}`,
          type: 'cookbook_invitation',
          read_at: null,
          created_at: new Date().toISOString(),
          data: {
            type: 'cookbook_invitation',
            status: 'pending',
            invitation: invitation.invitation as CookbookInvitationNotification['data']['invitation'],
          },
        });
      }
    },

    receiveNotification(payload: unknown): void {
      const notification = notificationFromBroadcast(payload as NotificationEvent);
      if (notification === null) return;
      this.upsertNotification(notification);
      const senderId = notification.type === 'cookbook_message'
        || notification.type === 'recipe_comment'
        || notification.type === 'recipe_comment_reply'
        ? notification.data.sender.id
        : null;
      if (senderId !== null && senderId === this.currentUserId) return;
      if (!displayedNotificationIds.has(notification.id)) {
        displayedNotificationIds.add(notification.id);
        this.addToast(notification);
      }
    },

    upsertNotification(notification: AppNotification): void {
      const invitationId = notification.type === 'cookbook_invitation'
        ? String(notification.data.invitation.id)
        : null;
      const invitationStatus = notification.type === 'cookbook_invitation' ? notification.data.status : null;
      const index = this.notifications.findIndex((item) => item.id === notification.id
        || (invitationId !== null && item.type === 'cookbook_invitation'
          && String(item.data.invitation.id) === invitationId
          && item.data.status === invitationStatus));
      if (index === -1) {
        this.notifications = [notification, ...this.notifications];
        if (notification.read_at === null) this.unreadCount += 1;
      } else {
        const previous = this.notifications[index];
        if (previous && previous.read_at === null && notification.read_at !== null) this.unreadCount = Math.max(0, this.unreadCount - 1);
        if (previous && previous.read_at !== null && notification.read_at === null) this.unreadCount += 1;
        this.notifications[index] = notification;
      }
    },

    addToast(notification: AppNotification): void {
      const toast: RealtimeToast = {
        id: `toast-${notification.id}`,
        title: notification.type === 'cookbook_message'
          ? 'Nouveau message'
          : notification.type === 'cookbook_invitation'
            ? 'Nouvelle invitation'
            : notification.type === 'recipe_comment_reply' ? 'Nouvelle réponse' : 'Nouveau commentaire',
        message: notification.type === 'cookbook_message'
          ? `${notification.data.sender.name} · ${notification.data.message.preview}`
          : notification.type === 'cookbook_invitation'
            ? notification.data.invitation.cookbook.name
            : `${notification.data.sender.name} · ${notification.data.recipe.title}`,
        notification,
      };
      this.toasts = [...this.toasts, toast].slice(-3);
    },

    dismissToast(id: string): void {
      this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },

    setNotifications(notifications: AppNotification[], unreadCount: number): void {
      this.notifications = notifications;
      this.unreadCount = unreadCount;
    },

    setMessages(cookbookId: string, messages: CookbookMessage[]): void {
      this.messagesByCookbook[cookbookId] = messages;
    },

    upsertMessage(cookbookId: string, message: CookbookMessage): void {
      const messages = this.messagesByCookbook[cookbookId] ?? [];
      const index = messages.findIndex((item) => item.id === message.id);
      this.messagesByCookbook[cookbookId] = index === -1 ? [...messages, message] : messages.map((item, itemIndex) => itemIndex === index ? message : item);
    },

    setComments(recipeId: string, comments: RecipeComment[]): void {
      this.commentsByRecipe[recipeId] = comments;
    },
  },
});

function isAppComment(value: unknown): value is RecipeComment {
  return typeof value === 'object' && value !== null && typeof (value as { id?: unknown }).id === 'string';
}
