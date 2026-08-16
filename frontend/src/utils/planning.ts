import { apiFetch } from '@/utils/api';

export type PlannedMeal = {
  id: string;
  date: string;
  meal_type: 'breakfast' | 'lunch' | 'dinner' | 'snack';
  note: string | null;
  initial_servings: number;
  servings: number;
  cookbook_id: string | null;
  recurrence: { id: string; frequency: 'weekly'; until: string } | null;
  recipe: {
    id: string;
    title: string;
    slug: string | null;
    servings: number | null;
    image_url: string | null;
    ingredients?: PlannedMealIngredient[];
  };
  created_at: string | null;
};

export type PlannedMealIngredient = {
  position: number;
  name: string;
  quantity: number | null;
  unit: string | null;
  preparation: string | null;
  is_optional: boolean;
  group_name: string | null;
};

type PlannedMealsPayload = { success: true; data: PlannedMeal[] } | {
  success: false;
  error?: { message?: string };
};

export type PlannedMealsResult =
  | { ok: true; meals: PlannedMeal[] }
  | { ok: false; message: string };

export type ShoppingListItem = {
  name: string;
  quantity: number | null;
  unit: string | null;
  preparation: string | null;
  is_optional: boolean;
};

type ShoppingListPayload = { success: true; data: ShoppingListItem[] } | {
  success: false;
  error?: { message?: string };
};

export type ShoppingListResult =
  | { ok: true; items: ShoppingListItem[] }
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
  input: Pick<PlannedMeal, 'date' | 'meal_type' | 'note' | 'servings'>,
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
  scope: 'occurrence' | 'series' = 'occurrence',
): Promise<DeletePlannedMealResult> {
  try {
    const query = scope === 'series' ? '?scope=series' : '';
    const response = await apiFetch(`/api/planned-meals/${encodeURIComponent(id)}${query}`, {
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
  cookbookId?: string,
): Promise<PlannedMealsResult> {
  const params = new URLSearchParams({ from, to });
  if (cookbookId) params.set('cookbook_id', cookbookId);

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

export async function fetchShoppingList(
  from: string,
  to: string,
  tokenType: string,
  accessToken: string,
): Promise<ShoppingListResult> {
  const params = new URLSearchParams({ from, to });

  try {
    const response = await apiFetch(`/api/planned-meals/shopping-list?${params.toString()}`, {
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as ShoppingListPayload | null;

    if (response.ok && payload?.success === true) return { ok: true, items: payload.data };

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de charger la liste de courses.')
        : 'Impossible de charger la liste de courses.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}
