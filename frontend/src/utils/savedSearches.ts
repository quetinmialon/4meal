import { apiFetch } from '@/utils/api';
import type { RecipeFilters } from '@/utils/recipes';

export type SavedSearchCriteria = RecipeFilters & { q?: string };

export type SavedSearch = {
  id: string;
  name: string;
  criteria: SavedSearchCriteria;
  created_at: string | null;
  updated_at: string | null;
};

type ApiErrorPayload = {
  success: false;
  error?: { message?: string };
};

type SavedSearchPayload = { success: true; data: SavedSearch };
type SavedSearchListPayload = { success: true; data: SavedSearch[] };

export type SavedSearchResult =
  | { ok: true; savedSearch: SavedSearch }
  | { ok: false; message: string };

export type SavedSearchListResult =
  | { ok: true; savedSearches: SavedSearch[] }
  | { ok: false; message: string };

function headers(tokenType: string, accessToken: string): HeadersInit {
  return { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` };
}

function errorMessage(payload: ApiErrorPayload | null, fallback: string): string {
  return payload?.success === false ? (payload.error?.message ?? fallback) : fallback;
}

export async function fetchSavedSearches(tokenType: string, accessToken: string): Promise<SavedSearchListResult> {
  try {
    const response = await apiFetch('/api/saved-searches', { headers: headers(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as SavedSearchListPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true && Array.isArray(payload.data)) {
      return { ok: true, savedSearches: payload.data };
    }
    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de charger les recherches sauvegardées.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function createSavedSearch(
  name: string,
  criteria: SavedSearchCriteria,
  tokenType: string,
  accessToken: string,
): Promise<SavedSearchResult> {
  try {
    const response = await apiFetch('/api/saved-searches', {
      method: 'POST',
      headers: { ...headers(tokenType, accessToken), 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name.trim(), criteria }),
    });
    const payload = (await response.json().catch(() => null)) as SavedSearchPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, savedSearch: payload.data };
    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de sauvegarder la recherche.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function deleteSavedSearch(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<{ ok: true } | { ok: false; message: string }> {
  try {
    const response = await apiFetch(`/api/saved-searches/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: headers(tokenType, accessToken),
    });
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;
    if (response.status === 204 || (response.ok && payload?.success !== false)) return { ok: true };
    return { ok: false, message: errorMessage(payload?.success === false ? payload : null, 'Impossible de supprimer la recherche sauvegardée.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}
