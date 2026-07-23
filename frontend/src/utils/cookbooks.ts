export type Cookbook = {
  id: string;
  name: string;
  slug: string | null;
  description: string | null;
  image_path: string | null;
  image_url: string | null;
  owner: {
    id: number;
    name: string;
    email: string;
    avatar_path: string | null;
    last_login_at: string | null;
    created_at: string | null;
  };
  created_at: string | null;
  member_role: string | null;
};

export type Recipe = {
  id: string;
  title: string;
  slug: string | null;
  description: string | null;
  prep_time_minutes: number | null;
  cook_time_minutes: number | null;
  rest_time_minutes: number | null;
  servings: number | null;
  image_path: string | null;
  visibility: string | null;
  difficulty: string | null;
  notes: string | null;
  created_at: string | null;
};

export type Pagination = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
  has_more_pages: boolean;
};

type PaginatedPayload<T> = {
  success: true;
  data: T[];
  meta: { pagination: Pagination };
};

type CreateCookbookPayload = {
  success: true;
  data: Cookbook;
};

type ApiErrorPayload = {
  success: false;
  error?: {
    message?: string;
    details?: {
      fields?: Record<string, string[]>;
    };
  };
};

export type CreateCookbookResult =
  | { ok: true; cookbook: Cookbook }
  | { ok: false; message: string; fieldErrors: { name?: string; slug?: string; description?: string; image?: string } };

export type UpdateCookbookResult =
  | { ok: true; cookbook: Cookbook }
  | { ok: false; message: string; fieldErrors: { name?: string; slug?: string; description?: string; image?: string } };

export type DeleteCookbookResult =
  | { ok: true }
  | { ok: false; message: string; fieldErrors: { confirmation?: string } };

export type ListResult<T> =
  | { ok: true; data: T[]; pagination: Pagination }
  | { ok: false; message: string };

export type CookbookInput = {
  name: string;
  slug?: string | null;
  description?: string | null;
  image?: File | null;
};

export async function createCookbook(
  input: CookbookInput,
  tokenType: string,
  accessToken: string,
): Promise<CreateCookbookResult> {
  try {
    const response = await fetch('/api/cookbooks', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
      },
      body: (() => {
        const formData = new FormData();
        formData.append('name', input.name.trim());
        if (input.slug?.trim()) formData.append('slug', input.slug.trim());
        if (input.description?.trim()) formData.append('description', input.description.trim());
        if (input.image instanceof File) formData.append('image', input.image);
        return formData;
      })(),
    });

    const payload = (await response.json().catch(() => null)) as
      | CreateCookbookPayload
      | ApiErrorPayload
      | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, cookbook: payload.data };
    }

    const fields = payload?.success === false ? payload.error?.details?.fields : undefined;

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de creer le cookbook.')
        : 'Impossible de creer le cookbook.',
      fieldErrors:
        typeof fields?.name?.[0] === 'string'
          ? { name: fields.name[0] }
          : {},
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
      fieldErrors: {},
    };
  }
}

export async function fetchCookbook(
  id: string,
  tokenType: string,
  accessToken: string,
): Promise<{ ok: true; cookbook: Cookbook } | { ok: false; message: string }> {
  try {
    const response = await fetch(`/api/cookbooks/${encodeURIComponent(id)}`, {
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
      },
    });
    const payload = (await response.json().catch(() => null)) as
      | CreateCookbookPayload
      | ApiErrorPayload
      | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, cookbook: payload.data };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de charger le cookbook.')
        : 'Impossible de charger le cookbook.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function updateCookbook(
  id: string,
  input: CookbookInput,
  tokenType: string,
  accessToken: string,
): Promise<UpdateCookbookResult> {
  try {
    const response = await fetch(`/api/cookbooks/${encodeURIComponent(id)}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
      },
      body: (() => {
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('name', input.name.trim());
        if (input.slug?.trim()) formData.append('slug', input.slug.trim());
        if (input.description?.trim()) formData.append('description', input.description.trim());
        if (input.image instanceof File) formData.append('image', input.image);
        return formData;
      })(),
    });
    const payload = (await response.json().catch(() => null)) as CreateCookbookPayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, cookbook: payload.data };
    }

    const fields = payload?.success === false ? payload.error?.details?.fields : undefined;

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de modifier le cookbook.')
        : 'Impossible de modifier le cookbook.',
      fieldErrors: {
        ...(typeof fields?.name?.[0] === 'string' ? { name: fields.name[0] } : {}),
        ...(typeof fields?.slug?.[0] === 'string' ? { slug: fields.slug[0] } : {}),
        ...(typeof fields?.description?.[0] === 'string' ? { description: fields.description[0] } : {}),
        ...(typeof fields?.image?.[0] === 'string' ? { image: fields.image[0] } : {}),
      },
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
      fieldErrors: {},
    };
  }
}

export async function deleteCookbook(
  id: string,
  confirmation: string,
  tokenType: string,
  accessToken: string,
): Promise<DeleteCookbookResult> {
  try {
    const response = await fetch(`/api/cookbooks/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ confirmation: confirmation.trim() }),
    });
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

    if (response.status === 204 || (response.ok && payload?.success !== false)) {
      return { ok: true };
    }

    const fields = payload?.success === false ? payload.error?.details?.fields : undefined;

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de supprimer le cookbook.')
        : 'Impossible de supprimer le cookbook.',
      fieldErrors: typeof fields?.confirmation?.[0] === 'string'
        ? { confirmation: fields.confirmation[0] }
        : {},
    };
  } catch {
    return {
      ok: false,
      message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
      fieldErrors: {},
    };
  }
}

function apiHeaders(tokenType: string, accessToken: string): HeadersInit {
  return {
    Accept: 'application/json',
    Authorization: `${tokenType} ${accessToken}`,
  };
}

async function fetchPaginated<T>(url: string, tokenType: string, accessToken: string): Promise<ListResult<T>> {
  try {
    const response = await fetch(url, { headers: apiHeaders(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as PaginatedPayload<T> | ApiErrorPayload | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, data: payload.data, pagination: payload.meta.pagination };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de charger les donnees.')
        : 'Impossible de charger les donnees.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export function fetchCookbooks(tokenType: string, accessToken: string, page = 1): Promise<ListResult<Cookbook>> {
  return fetchPaginated(`/api/cookbooks?page=${page}`, tokenType, accessToken);
}

export function fetchCookbookRecipes(
  id: string,
  tokenType: string,
  accessToken: string,
  page = 1,
): Promise<ListResult<Recipe>> {
  return fetchPaginated(
    `/api/cookbooks/${encodeURIComponent(id)}/recipes?page=${page}`,
    tokenType,
    accessToken,
  );
}
