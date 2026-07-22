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
