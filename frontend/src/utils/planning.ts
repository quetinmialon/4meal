import { apiFetch } from '@/utils/api';

export type PlannedMeal = {
  id: string;
  date: string;
  meal_type: 'breakfast' | 'lunch' | 'dinner' | 'snack';
  note: string | null;
  initial_servings: number;
  cookbook_id: string | null;
  recipe: {
    id: string;
    title: string;
    slug: string | null;
    servings: number | null;
    image_url: string | null;
  };
  created_at: string | null;
};

type PlannedMealsPayload = { success: true; data: PlannedMeal[] } | {
  success: false;
  error?: { message?: string };
};

export type PlannedMealsResult =
  | { ok: true; meals: PlannedMeal[] }
  | { ok: false; message: string };

type PlannedMealMutationPayload = {
  success: true;
  data: PlannedMeal;
} | {
  success: false;
  error?: { message?: string; details?: { fields?: Record<string, string[]> } };
};

export type PlannedMealMutationResult =
  | { ok: true; meal: PlannedMeal }
  | { ok: false; message: string; fieldErrors: Record<string, string> };

function mutationError(payload: PlannedMealMutationPayload | null, fallback: string) {
  const fields = payload?.success === false ? payload.error?.details?.fields : undefined;

  return {
    message: payload?.success === false ? (payload.error?.message ?? fallback) : fallback,
    fieldErrors: Object.fromEntries(
      Object.entries(fields ?? {}).map(([field, messages]) => [field, messages[0] ?? 'Valeur invalide.']),
    ),
  };
}

export async function updatePlannedMeal(
  id: string,
  input: Pick<PlannedMeal, 'date' | 'meal_type' | 'note'>,
  tokenType: string,
  accessToken: string,
): Promise<PlannedMealMutationResult> {
  try {
    const response = await apiFetch(`/api/planned-meals/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(input),
    });
    const payload = (await response.json().catch(() => null)) as PlannedMealMutationPayload | null;

    if (response.ok && payload?.success === true) return { ok: true, meal: payload.data };

    return { ok: false, ...mutationError(payload, 'Impossible de modifier le repas planifié.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.', fieldErrors: {} };
  }
}

export type DeletePlannedMealResult = { ok: true } | { ok: false; message: string };

export async function deletePlannedMeal(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<DeletePlannedMealResult> {
  try {
    const response = await apiFetch(`/api/planned-meals/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as PlannedMealMutationPayload | null;

    if (response.status === 204 || (response.ok && payload?.success !== false)) return { ok: true };

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de supprimer le repas planifié.')
        : 'Impossible de supprimer le repas planifié.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function fetchPlannedMeals(
  from: string,
  to: string,
  tokenType: string,
  accessToken: string,
): Promise<PlannedMealsResult> {
  const params = new URLSearchParams({ from, to });

  try {
    const response = await apiFetch(`/api/planned-meals?${params.toString()}`, {
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as PlannedMealsPayload | null;

    if (response.ok && payload?.success === true) return { ok: true, meals: payload.data };

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de charger le planning.')
        : 'Impossible de charger le planning.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}
