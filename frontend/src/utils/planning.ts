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

export async function fetchPlannedMeals(
  from: string,
  to: string,
  tokenType: string,
  accessToken: string,
): Promise<PlannedMealsResult> {
  const params = new URLSearchParams({ from, to });

  try {
    const response = await fetch(`/api/planned-meals?${params.toString()}`, {
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
