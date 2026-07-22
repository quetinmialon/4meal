export type Cookbook = {
  id: string;
  name: string;
  owner: {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
  };
  created_at: string | null;
  member_role: string | null;
};

export type Recipe = {
  id: string;
  name: string;
  description: string | null;
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
  | { ok: false; message: string; fieldErrors: { name?: string } };

export type UpdateCookbookResult =
  | { ok: true; cookbook: Cookbook }
  | { ok: false; message: string; fieldErrors: { name?: string } };

export type ListResult<T> =
  | { ok: true; data: T[]; pagination: Pagination }
  | { ok: false; message: string };

export async function createCookbook(
  name: string,
  tokenType: string,
  accessToken: string,
): Promise<CreateCookbookResult> {
  try {
    const response = await fetch('/api/cookbooks', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ name: name.trim() }),
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
  name: string,
  tokenType: string,
  accessToken: string,
): Promise<UpdateCookbookResult> {
  try {
    const response = await fetch(`/api/cookbooks/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        Authorization: `${tokenType} ${accessToken}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ name: name.trim() }),
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
      fieldErrors: typeof fields?.name?.[0] === 'string' ? { name: fields.name[0] } : {},
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
