export type OAuthProvider = 'google' | 'microsoft';

export type OAuthAccount = {
  id: number;
  provider: OAuthProvider;
  email: string;
  token_expires_at: string | null;
  created_at: string | null;
};

type ApiErrorPayload = {
  success: false;
  error?: {
    message?: string;
    details?: { fields?: Record<string, string[]> };
  };
};

type OAuthAccountsPayload = {
  success: true;
  data: OAuthAccount[];
};

type OAuthAccountsResult =
  | { ok: true; accounts: OAuthAccount[] }
  | { ok: false; message: string };

type OAuthActionResult = { ok: true } | { ok: false; message: string };

function headers(tokenType: string, accessToken: string): HeadersInit {
  return {
    Accept: 'application/json',
    Authorization: `${tokenType} ${accessToken}`,
  };
}

function providerPath(provider: OAuthProvider): string {
  return encodeURIComponent(provider);
}

export async function fetchOAuthAccounts(tokenType: string, accessToken: string): Promise<OAuthAccountsResult> {
  try {
    const response = await fetch('/api/auth/oauth-accounts', { headers: headers(tokenType, accessToken) });
    const payload = (await response.json().catch(() => null)) as OAuthAccountsPayload | ApiErrorPayload | null;

    if (response.ok && payload?.success === true && Array.isArray(payload.data)) {
      return { ok: true, accounts: payload.data };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? 'Impossible de charger les comptes associés.')
        : 'Impossible de charger les comptes associés.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function unlinkOAuthAccount(
  provider: OAuthProvider,
  tokenType: string,
  accessToken: string,
): Promise<OAuthActionResult> {
  try {
    const response = await fetch(`/api/auth/oauth/${providerPath(provider)}`, {
      method: 'DELETE',
      headers: headers(tokenType, accessToken),
    });
    const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

    if (response.ok && payload?.success !== false) return { ok: true };

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.details?.fields?.provider?.[0]
          ?? payload.error?.message
          ?? 'Impossible de supprimer ce compte associé.')
        : 'Impossible de supprimer ce compte associé.',
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export async function startOAuthLink(
  provider: OAuthProvider,
  tokenType: string,
  accessToken: string,
): Promise<OAuthActionResult> {
  try {
    const response = await fetch(oauthLinkUrl(provider), {
      headers: headers(tokenType, accessToken),
    });

    const payload = (await response.json().catch(() => null)) as
      | { success: true; data?: { authorization_url?: string } }
      | ApiErrorPayload
      | null;

    if (response.ok && payload?.success === true && typeof payload.data?.authorization_url === 'string') {
      window.location.assign(payload.data.authorization_url);
      return { ok: true };
    }

    return {
      ok: false,
      message: payload?.success === false
        ? (payload.error?.message ?? `Impossible d’associer ${oauthProviderLabel(provider)}.`)
        : `Impossible d’associer ${oauthProviderLabel(provider)}.`,
    };
  } catch {
    return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
  }
}

export function oauthLinkUrl(provider: OAuthProvider): string {
  return `/api/auth/oauth/${providerPath(provider)}/link`;
}

export function oauthProviderLabel(provider: OAuthProvider): string {
  return provider === 'google' ? 'Google' : 'Microsoft';
}
