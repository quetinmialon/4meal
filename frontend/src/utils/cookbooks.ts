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

export type CookbookMember = {
  user: {
    id: number;
    name: string;
    email: string;
  };
  role: string;
  joined_at: string | null;
  status: string;
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
    code?: string;
    message?: string;
    details?: {
      fields?: Record<string, string[]>;
    };
  };
};

export type CookbookInvitation = {
  id: number;
  email: string;
  role: 'editor' | 'viewer';
  expires_at: string;
  accepted_at: string | null;
  cookbook: { id: string; name: string };
};

type InvitationPayload = { success: true; data: CookbookInvitation };
type AcceptedInvitationPayload = {
  success: true;
  data: { invitation: { id: number; accepted_at: string }; cookbook: { id: string; name: string; role: 'editor' | 'viewer' } };
};

export type InvitationResult =
  | { ok: true; invitation: CookbookInvitation }
  | { ok: false; message: string; fieldErrors: { email?: string; role?: string }; expired?: boolean };

export type AcceptInvitationResult =
  | { ok: true; cookbook: { id: string; name: string; role: 'editor' | 'viewer' }; acceptedAt: string }
  | { ok: false; message: string; expired?: boolean; unauthorized?: boolean };

export type InvitationListResult =
  | { ok: true; invitations: CookbookInvitation[] }
  | { ok: false; message: string };

function isCookbookInvitation(value: unknown): value is CookbookInvitation {
  if (typeof value !== 'object' || value === null) return false;
  const invitation = value as Partial<CookbookInvitation>;
  return typeof invitation.id === 'number'
    && typeof invitation.email === 'string'
    && (invitation.role === 'editor' || invitation.role === 'viewer')
    && typeof invitation.expires_at === 'string'
    && typeof invitation.cookbook?.id === 'string'
    && typeof invitation.cookbook.name === 'string';
}

function invitationError(response: Response, payload: ApiErrorPayload | null, fallback: string) {
  const fields = payload?.success === false ? payload.error?.details?.fields : undefined;
  return {
    message: payload?.success === false ? (payload.error?.message ?? fallback) : fallback,
    fieldErrors: {
      ...(typeof fields?.email?.[0] === 'string' ? { email: fields.email[0] } : {}),
      ...(typeof fields?.role?.[0] === 'string' ? { role: fields.role[0] } : {}),
    },
    ...(response.status === 410 ? { expired: true } : {}),
  };
}

export async function createCookbookInvitation(
  cookbookId: string, email: string, role: 'editor' | 'viewer', tokenType: string, accessToken: string,
): Promise<InvitationResult> {
  try {
    const response = await fetch(`/api/cookbooks/${encodeURIComponent(cookbookId)}/invitations`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.trim().toLowerCase(), role }),
    });
    const payload = (await response.json().catch(() => null)) as InvitationPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, invitation: payload.data };
    return { ok: false, ...invitationError(response, payload?.success === false ? payload : null, 'Impossible d’envoyer l’invitation.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.', fieldErrors: {} };
  }
}

export async function fetchCookbookInvitation(token: string): Promise<InvitationResult> {
  try {
    const response = await fetch(`/api/invitations/${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' } });
    const payload = (await response.json().catch(() => null)) as InvitationPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, invitation: payload.data };
    return { ok: false, ...invitationError(response, payload?.success === false ? payload : null, 'Impossible de charger cette invitation.') };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.', fieldErrors: {} };
  }
}

export async function fetchCookbookInvitations(tokenType: string, accessToken: string): Promise<InvitationListResult> {
  try {
    const response = await fetch('/api/invitations', { headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` } });
    const payload = (await response.json().catch(() => null)) as { success: true; data: CookbookInvitation[] } | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, invitations: payload.data.filter(isCookbookInvitation) };
    return { ok: false, message: payload?.success === false ? (payload.error?.message ?? 'Impossible de charger les invitations.') : 'Impossible de charger les invitations.' };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function acceptCookbookInvitationById(
  id: number, tokenType: string, accessToken: string,
): Promise<AcceptInvitationResult> {
  try {
    const response = await fetch(`/api/invitations/${id}/accept`, {
      method: 'POST', headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as AcceptedInvitationPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, cookbook: payload.data.cookbook, acceptedAt: payload.data.invitation.accepted_at };
    return { ok: false, message: payload?.success === false ? (payload.error?.message ?? 'Impossible d’accepter l’invitation.') : 'Impossible d’accepter l’invitation.', ...(response.status === 410 ? { expired: true } : {}) };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function declineCookbookInvitation(id: number, tokenType: string, accessToken: string): Promise<{ ok: true } | { ok: false; message: string; expired?: boolean }> {
  try {
    const response = await fetch(`/api/invitations/${id}/decline`, {
      method: 'POST', headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as { success: true } | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true };
    return { ok: false, message: payload?.success === false ? (payload.error?.message ?? 'Impossible de refuser l’invitation.') : 'Impossible de refuser l’invitation.', ...(response.status === 410 ? { expired: true } : {}) };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function acceptCookbookInvitation(
  token: string, tokenType: string, accessToken: string,
): Promise<AcceptInvitationResult> {
  try {
    const response = await fetch(`/api/invitations/token/${encodeURIComponent(token)}/accept`, {
      method: 'POST', headers: { Accept: 'application/json', Authorization: `${tokenType} ${accessToken}` },
    });
    const payload = (await response.json().catch(() => null)) as AcceptedInvitationPayload | ApiErrorPayload | null;
    if (response.ok && payload?.success === true) return { ok: true, cookbook: payload.data.cookbook, acceptedAt: payload.data.invitation.accepted_at };
    return {
      ok: false,
      message: payload?.success === false ? (payload.error?.message ?? 'Impossible d’accepter l’invitation.') : 'Impossible d’accepter l’invitation.',
      ...(response.status === 410 ? { expired: true } : {}),
      ...(response.status === 401 ? { unauthorized: true } : {}),
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

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

export type CookbookRecipeActionResult =
  | { ok: true }
  | { ok: false; message: string };

export async function addRecipeToCookbook(
  cookbookId: string,
  recipeId: string,
  tokenType: string,
  accessToken: string,
): Promise<CookbookRecipeActionResult> {
  try {
    const response = await fetch(
      `/api/cookbooks/${encodeURIComponent(cookbookId)}/recipes/${encodeURIComponent(recipeId)}`,
      { method: 'POST', headers: apiHeaders(tokenType, accessToken) },
    );
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;
    if (response.status === 204 || (response.ok && payload?.success !== false)) return { ok: true };
    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible d’ajouter la recette.')
        : 'Impossible d’ajouter la recette.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export async function removeRecipeFromCookbook(
  cookbookId: string,
  recipeId: string,
  tokenType: string,
  accessToken: string,
): Promise<CookbookRecipeActionResult> {
  try {
    const response = await fetch(
      `/api/cookbooks/${encodeURIComponent(cookbookId)}/recipes/${encodeURIComponent(recipeId)}`,
      { method: 'DELETE', headers: apiHeaders(tokenType, accessToken) },
    );
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;
    if (response.status === 204 || (response.ok && payload?.success !== false)) return { ok: true };
    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de retirer la recette.')
        : 'Impossible de retirer la recette.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export function fetchCookbookMembers(
  id: string,
  tokenType: string,
  accessToken: string,
  page = 1,
): Promise<ListResult<CookbookMember>> {
  return fetchPaginated(
    `/api/cookbooks/${encodeURIComponent(id)}/members?page=${page}`,
    tokenType,
    accessToken,
  );
}

export type UpdateCookbookMemberRoleResult =
  | { ok: true; member: CookbookMember }
  | { ok: false; message: string };

export async function updateCookbookMemberRole(
  cookbookId: string,
  memberId: number,
  role: string,
  tokenType: string,
  accessToken: string,
): Promise<UpdateCookbookMemberRoleResult> {
  try {
    const response = await fetch(
      `/api/cookbooks/${encodeURIComponent(cookbookId)}/members/${encodeURIComponent(memberId)}/role`,
      {
        method: 'PATCH',
        headers: { ...apiHeaders(tokenType, accessToken), 'Content-Type': 'application/json' },
        body: JSON.stringify({ role }),
      },
    );
    const payload = (await response.json().catch(() => null)) as
      | { success: true; data: CookbookMember }
      | ApiErrorPayload
      | null;

    if (response.ok && payload?.success === true) {
      return { ok: true, member: payload.data };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de modifier le rôle du membre.')
        : 'Impossible de modifier le rôle du membre.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export type CookbookMemberActionResult =
  | { ok: true }
  | { ok: false; message: string };

async function deleteCookbookMemberAction(
  url: string,
  tokenType: string,
  accessToken: string,
): Promise<CookbookMemberActionResult> {
  try {
    const response = await fetch(url, {
      method: 'DELETE',
      headers: apiHeaders(tokenType, accessToken),
    });
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

    if (response.status === 204 || (response.ok && payload?.success !== false)) {
      return { ok: true };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de modifier les membres du cookbook.')
        : 'Impossible de modifier les membres du cookbook.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
  }
}

export function leaveCookbook(
  cookbookId: string,
  tokenType: string,
  accessToken: string,
): Promise<CookbookMemberActionResult> {
  return deleteCookbookMemberAction(
    `/api/cookbooks/${encodeURIComponent(cookbookId)}/members/me`,
    tokenType,
    accessToken,
  );
}

export function removeCookbookMember(
  cookbookId: string,
  memberId: number,
  tokenType: string,
  accessToken: string,
): Promise<CookbookMemberActionResult> {
  return deleteCookbookMemberAction(
    `/api/cookbooks/${encodeURIComponent(cookbookId)}/members/${encodeURIComponent(memberId)}`,
    tokenType,
    accessToken,
  );
}
