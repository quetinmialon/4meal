import { apiFetch } from '@/utils/api';

export type NotificationChannel = 'none' | 'web' | 'mail' | 'both';
export type NotificationType = 'recipe_comment' | 'recipe_comment_reply' | 'cookbook_message';

export type NotificationPreference = {
  type: NotificationType;
  channel: NotificationChannel;
};

type PreferencesPayload = { success: true; data: NotificationPreference[] };
type ErrorPayload = { success: false; error?: { message?: string } };

function headers(tokenType: string, accessToken: string): HeadersInit {
  return { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` };
}

function errorMessage(payload: ErrorPayload | null, fallback: string): string {
  return payload?.error?.message ?? fallback;
}

export async function fetchNotificationPreferences(tokenType: string, accessToken: string): Promise<
  | { ok: true; preferences: NotificationPreference[] }
  | { ok: false; message: string }
> {
  try {
    const response = await apiFetch('/api/notifications/preferences', { headers: headers(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as PreferencesPayload | ErrorPayload | null;
    if (response.ok && payload?.success === true && Array.isArray(payload.data)) return { ok: true, preferences: payload.data };
    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de charger les préférences de notifications.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function updateNotificationPreferences(
  tokenType: string,
  accessToken: string,
  preferences: NotificationPreference[],
): Promise<{ ok: true; preferences: NotificationPreference[] } | { ok: false; message: string }> {
  try {
    const response = await apiFetch('/api/notifications/preferences', {
      method: 'PUT',
      headers: { ...headers(tokenType, accessToken), 'Content-Type': 'application/json' },
      body: JSON.stringify({ preferences }),
    });
    const payload = (await response.json().catch(() => null)) as PreferencesPayload | ErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, preferences: payload.data };
    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible d’enregistrer les préférences de notifications.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}
