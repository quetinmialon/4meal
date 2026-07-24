export type RecipeIngredientInput = {
  name: string;
  quantity: number | null;
  unit: string;
  preparation: string;
  is_optional: boolean;
  group_name: string;
};

export type RecipeStepInput = {
  instruction: string;
  duration_minutes: number | null;
};

export type RecipeInput = {
  title: string;
  description?: string | null;
  prep_time_minutes: number | null;
  cook_time_minutes: number | null;
  servings: number | null;
  source: string;
  cookbook_id: string | null;
  ingredients: RecipeIngredientInput[];
  steps: RecipeStepInput[];
  tags: string[];
};

export type CreatedRecipe = {
  id: string;
  title: string;
  slug: string | null;
};

export type RecipeUser = {
  id: number;
  name: string;
  email?: string;
  avatar_path?: string | null;
};

export type RecipeIngredient = RecipeIngredientInput & {
  position: number;
};

export type RecipeStep = RecipeStepInput & {
  position: number;
};

export type RecipeTag = {
  id: number;
  name: string;
  slug: string;
  color: string | null;
};

export type Recipe = {
  id: string;
  title: string;
  name?: string;
  slug: string | null;
  description: string | null;
  prep_time_minutes: number | null;
  cook_time_minutes: number | null;
  rest_time_minutes?: number | null;
  servings: number | null;
  notes?: string | null;
  source: string | null;
  created_at: string | null;
  author: RecipeUser | null;
  ingredients?: RecipeIngredient[];
  steps?: RecipeStep[];
  tags?: RecipeTag[];
};

export type RecipePagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
  has_more_pages: boolean;
};

type RecipePayload = { success: true; data: CreatedRecipe };
type RecipeListPayload = { success: true; data: Recipe[]; meta: { pagination: RecipePagination } };
type RecipeDetailPayload = { success: true; data: Recipe };
type ApiErrorPayload = {
  success: false;
  error?: {
    message?: string;
    details?: { fields?: Record<string, string[]> };
  };
};

export type CreateRecipeResult =
  | { ok: true; recipe: CreatedRecipe }
  | { ok: false; message: string; fieldErrors: Record<string, string> };

export type RecipeListResult =
  | { ok: true; recipes: Recipe[]; pagination: RecipePagination }
  | { ok: false; message: string };

export type RecipeDetailResult =
  | { ok: true; recipe: Recipe }
  | { ok: false; message: string };

function recipeReadHeaders(tokenType: string, accessToken: string): HeadersInit {
  return { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` };
}

function readError(payload: ApiErrorPayload | null, fallback: string): string {
  return payload?.success === false ? (payload.error?.message ?? fallback) : fallback;
}

export async function fetchRecipes(
  tokenType: string,
  accessToken: string,
  page = 1,
): Promise<RecipeListResult> {
  try {
    const response = await fetch(`/api/recipes?page=${page}`, { headers: recipeReadHeaders(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as RecipeListPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) {
      return { ok: true, recipes: payload.data, pagination: payload.meta.pagination };
    }
    return { ok: false, message: readError(payload?.success === false ? payload : null, 'Impossible de charger les recettes.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function fetchRecipe(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<RecipeDetailResult> {
  try {
    const response = await fetch(`/api/recipes/${encodeURIComponent(id)}`, { headers: recipeReadHeaders(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as RecipeDetailPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, recipe: payload.data };
    return { ok: false, message: readError(payload?.success === false ? payload : null, 'Impossible de charger la recette.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function createRecipe(
  input: RecipeInput,
  tokenType: string,
  accessToken: string,
): Promise<CreateRecipeResult> {
  try {
    const response = await fetch('/api/recipes', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        ...input,
        title: input.title.trim(),
        description: input.description?.trim() || null,
        source: input.source.trim() || null,
        cookbook_id: input.cookbook_id || null,
        ingredients: input.ingredients.map((ingredient) => ({
          ...ingredient,
          name: ingredient.name.trim(),
          unit: ingredient.unit.trim() || null,
          preparation: ingredient.preparation.trim() || null,
          group_name: ingredient.group_name.trim() || null,
        })),
        steps: input.steps.map((step) => ({ ...step, instruction: step.instruction.trim() })),
        tags: input.tags.map((tag) => tag.trim()).filter(Boolean),
      }),
    });
    const payload = (await response.json().catch(() => null)) as RecipePayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, recipe: payload.data };
    }

    const fields = payload?.success === false ? payload.error?.details?.fields : undefined;
    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de creer la recette.')
        : 'Impossible de creer la recette.',
      fieldErrors: Object.fromEntries(
        Object.entries(fields ?? {}).map(([key, messages]) => [key, messages[0] ?? 'Valeur invalide.']),
      ),
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
      fieldErrors: {},
    };
  }
}

export type UpdateRecipeResult =
  | { ok: true; recipe: CreatedRecipe }
  | { ok: false; message: string; fieldErrors: Record<string, string>; conflict?: boolean };

export async function updateRecipe(
  id: string,
  input: RecipeInput,
  tokenType: string,
  accessToken: string,
): Promise<UpdateRecipeResult> {
  try {
    const response = await fetch(`/api/recipes/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        title: input.title.trim(),
        description: input.description?.trim() || null,
        prep_time_minutes: input.prep_time_minutes,
        cook_time_minutes: input.cook_time_minutes,
        servings: input.servings,
        source: input.source.trim() || null,
        ingredients: input.ingredients.map((ingredient) => ({
          ...ingredient,
          name: ingredient.name.trim(),
          unit: ingredient.unit.trim() || null,
          preparation: ingredient.preparation.trim() || null,
          group_name: ingredient.group_name.trim() || null,
        })),
        steps: input.steps.map((step) => ({ ...step, instruction: step.instruction.trim() })),
        tags: input.tags.map((tag) => tag.trim()).filter(Boolean),
      }),
    });
    const payload = (await response.json().catch(() => null)) as RecipePayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, recipe: payload.data };
    }

    const fields = payload?.success === false ? payload.error?.details?.fields : undefined;
    const conflict = response.status === 409;
    return {
      ok: false,
      conflict,
      message: payload?.success === false
        ? (payload.error?.message ?? (conflict ? 'La recette a été modifiée ailleurs. Rechargez-la avant de réessayer.' : 'Impossible de modifier la recette.'))
        : (conflict ? 'La recette a été modifiée ailleurs. Rechargez-la avant de réessayer.' : 'Impossible de modifier la recette.'),
      fieldErrors: Object.fromEntries(
        Object.entries(fields ?? {}).map(([key, messages]) => [key, messages[0] ?? 'Valeur invalide.']),
      ),
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
      fieldErrors: {},
    };
  }
}

export type DeleteRecipeResult =
  | { ok: true }
  | { ok: false; message: string };

export async function deleteRecipe(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<DeleteRecipeResult> {
  try {
    const response = await fetch(`/api/recipes/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
      },
    });
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

    if (response.status === 204 || (response.ok && payload?.success !== false)) {
      return { ok: true };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de supprimer la recette.')
        : 'Impossible de supprimer la recette.',
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
    };
  }
}
