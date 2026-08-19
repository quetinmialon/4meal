import { apiFetch } from '@/utils/api';

type BaseNotification = {
  id: string;
  read_at: string | null;
  created_at: string | null;
};

export type MessageNotification = BaseNotification & {
  type: 'cookbook_message';
  data: {
    type: 'cookbook_message';
    cookbook: { id: string; name: string };
    message: { id: string; preview: string };
    sender: { id: number; name: string };
  };
};

export type RecipeCommentNotification = BaseNotification & {
  type: 'recipe_comment' | 'recipe_comment_reply';
  data: {
    type: 'recipe_comment' | 'recipe_comment_reply';
    recipe: { id: string; title: string };
    comment: { id: string; preview: string };
    sender: { id: number; name: string };
  };
};

export type CookbookInvitationNotification = BaseNotification & {
  type: 'cookbook_invitation';
  data: {
    type: 'cookbook_invitation';
    status: 'pending' | 'accepted' | 'declined';
    invitation: {
      id: number;
      role?: string;
      expires_at?: string;
      cookbook: { id: string; name: string };
      inviter?: { id: number; name: string };
      member?: { id: number; role: string };
      declined_by?: { id: number; name: string };
    };
  };
};

export type AppNotification = MessageNotification | RecipeCommentNotification | CookbookInvitationNotification;

type NotificationsPayload = {
  success: true;
  data: AppNotification[];
  meta: {
    unread_count: number;
  };
};

type NotificationPayload = { success: true; data: AppNotification };
type ApiErrorPayload = { success: false; error?: { message?: string } };

export type NotificationsResult =
  | { ok: true; notifications: AppNotification[]; unreadCount: number }
  | { ok: false; message: string };

export type MarkNotificationResult =
  | { ok: true; notification: AppNotification }
  | { ok: false; message: string };

export type MarkAllNotificationsResult =
  | { ok: true; markedCount: number; readAt: string | null }
  | { ok: false; message: string };

function authHeaders(tokenType: string, accessToken: string): HeadersInit {
  return { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` };
}

function errorMessage(payload: ApiErrorPayload | null, fallback: string): string {
  return payload?.success === false ? (payload.error?.message ?? fallback) : fallback;
}

function isSupportedNotification(notification: AppNotification): boolean {
  return notification.type === 'cookbook_message'
    || notification.type === 'recipe_comment'
    || notification.type === 'recipe_comment_reply'
    || notification.type === 'cookbook_invitation';
}

export async function fetchNotifications(tokenType: string, accessToken: string): Promise<NotificationsResult> {
  try {
    const response = await apiFetch('/api/notifications?per_page=20', { headers: authHeaders(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as NotificationsPayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) {
      return {
        ok: true,
        notifications: payload.data.filter(isSupportedNotification),
        unreadCount: payload.meta.unread_count,
      };
    }

    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de charger les notifications.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function markNotificationAsRead(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<MarkNotificationResult> {
  try {
    const response = await apiFetch(`/api/notifications/${encodeURIComponent(id)}/read`, {
      method: 'PATCH',
      headers: authHeaders(tokenType, accessToken),
    });
    const payload = (await response.json().catch(() => null)) as NotificationPayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) return { ok: true, notification: payload.data };

    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de marquer la notification comme lue.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function markAllNotificationsAsRead(
  tokenType: string,
  accessToken: string,
): Promise<MarkAllNotificationsResult> {
  try {
    const response = await apiFetch('/api/notifications/read-all', {
      method: 'PATCH',
      headers: authHeaders(tokenType, accessToken),
    });
    const payload = (await response.json().catch(() => null)) as
      | { success: true; data?: { marked_count?: number; read_at?: string | null } }
      | ApiErrorPayload
      | null;

    if (response.ok && payload?.success === true) {
      return {
        ok: true,
        markedCount: payload.data?.marked_count ?? 0,
        readAt: payload.data?.read_at ?? new Date().toISOString(),
      };
    }

    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de marquer les notifications comme lues.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}
