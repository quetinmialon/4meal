import { defineStore } from 'pinia';

import { apiFetch } from '@/utils/api';

const AUTH_STORAGE_KEY = '4meal.auth.session';
const TWO_FACTOR_STORAGE_KEY = '4meal.auth.two-factor';

type AuthStatus = 'idle' | 'loading' | 'restoring' | 'authenticated';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  email_verified?: boolean;
  two_factor_enabled?: boolean;
  avatar_path: string | null;
  avatar_url?: string | null;
  last_login_at: string | null;
  created_at: string | null;
  diet?: string | null;
  allergies?: string[];
  default_servings?: number;
};

type AuthSession = {
  accessToken: string;
  tokenType: string;
  expiresIn: number;
  user: AuthUser;
};

type LoginCredentials = {
  email: string;
  password: string;
};

type LoginSuccessPayload = {
  success: true;
  data: {
    access_token: string;
    token_type: string;
    expires_in: number;
    user: AuthUser;
  };
};

type TwoFactorRequiredPayload = {
  success: true;
  data: {
    two_factor_required: true;
    challenge: string;
    expires_in: number;
  };
};

export type PendingTwoFactorChallenge = {
  challenge: string;
  expiresIn: number;
  email: string;
};

type CurrentUserSuccessPayload = {
  success: true;
  data: AuthUser;
};

type ApiErrorPayload = {
  success: false;
  error?: {
    code?: string;
    message?: string;
    details?: {
      fields?: Record<string, string[]>;
      [key: string]: unknown;
    };
  };
};

export type LoginResult =
  | {
      ok: true;
      twoFactorRequired?: false;
    }
  | {
      ok: true;
      twoFactorRequired: true;
    }
  | {
      ok: false;
      message: string;
      fieldErrors: Partial<Record<'email' | 'password', string>>;
    };

export type ChangePasswordResult =
  | {
      ok: true;
    }
  | {
      ok: false;
      message: string;
      fieldErrors: Partial<Record<'current_password' | 'password' | 'password_confirmation', string>>;
    };

export type RequestPasswordResetResult =
  | {
      ok: true;
    }
  | {
      ok: false;
      message: string;
      fieldErrors: Partial<Record<'email', string>>;
    };

export type ResetPasswordResult =
  | {
      ok: true;
    }
  | {
      ok: false;
      message: string;
      fieldErrors: Partial<Record<'email' | 'token' | 'password' | 'password_confirmation', string>>;
    };

export type EmailVerificationResult =
  | { ok: true; user: AuthUser | null }
  | { ok: false; message: string };

export type ResendEmailVerificationResult =
  | { ok: true; message: string }
  | { ok: false; message: string };

export type UpdateProfileResult =
  | {
      ok: true;
    }
  | {
      ok: false;
      message: string;
      fieldErrors: Partial<Record<'name' | 'email' | 'avatar_path' | 'current_password' | 'diet' | 'allergies' | 'default_servings', string>>;
    };

export type TwoFactorActionResult =
  | { ok: true; enabled: boolean }
  | { ok: false; message: string };

export type VerifyTwoFactorResult =
  | { ok: true }
  | { ok: false; message: string };

type PersistedSession = {
  user: AuthUser;
};

let restoreSessionPromise: Promise<void> | null = null;

function isAuthUser(value: unknown): value is AuthUser {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as AuthUser).id === 'number' &&
    typeof (value as AuthUser).name === 'string' &&
    typeof (value as AuthUser).email === 'string' &&
    ((value as AuthUser).avatar_path === undefined ||
      (value as AuthUser).avatar_path === null ||
      typeof (value as AuthUser).avatar_path === 'string') &&
    ((value as AuthUser).avatar_url === undefined ||
      (value as AuthUser).avatar_url === null ||
      typeof (value as AuthUser).avatar_url === 'string') &&
    ((value as AuthUser).last_login_at === undefined ||
      (value as AuthUser).last_login_at === null ||
      typeof (value as AuthUser).last_login_at === 'string') &&
    (((value as AuthUser).created_at === null) || typeof (value as AuthUser).created_at === 'string')
  );
}

function readPersistedSession(): PersistedSession | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const rawSession = window.localStorage.getItem(AUTH_STORAGE_KEY);

  if (rawSession === null) {
    return null;
  }

  try {
    const parsed = JSON.parse(rawSession) as Partial<PersistedSession>;

    if (!isAuthUser(parsed.user)) {
      return null;
    }

    return { user: parsed.user };
  } catch {
    return null;
  }
}

function persistSession(session: PersistedSession | null): void {
  if (typeof window === 'undefined') {
    return;
  }

  if (session === null) {
    window.localStorage.removeItem(AUTH_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify({ user: session.user }));
}

function readPendingTwoFactor(): PendingTwoFactorChallenge | null {
  if (typeof window === 'undefined') return null;

  try {
    const value = JSON.parse(window.sessionStorage.getItem(TWO_FACTOR_STORAGE_KEY) ?? 'null') as Partial<PendingTwoFactorChallenge> | null;
    if (value === null || typeof value.challenge !== 'string' || value.challenge.length !== 64 || typeof value.expiresIn !== 'number' || typeof value.email !== 'string') return null;
    return { challenge: value.challenge, expiresIn: value.expiresIn, email: value.email };
  } catch {
    return null;
  }
}

function persistPendingTwoFactor(challenge: PendingTwoFactorChallenge | null): void {
  if (typeof window === 'undefined') return;
  if (challenge === null) window.sessionStorage.removeItem(TWO_FACTOR_STORAGE_KEY);
  else window.sessionStorage.setItem(TWO_FACTOR_STORAGE_KEY, JSON.stringify(challenge));
}

function extractFieldErrors(payload: ApiErrorPayload | null): Partial<Record<'email' | 'password', string>> {
  const fields = payload?.error?.details?.fields;

  if (fields === undefined) {
    return {};
  }

  return {
    email: typeof fields.email?.[0] === 'string' ? fields.email[0] : '',
    password: typeof fields.password?.[0] === 'string' ? fields.password[0] : '',
  };
}

function extractChangePasswordFieldErrors(
  payload: ApiErrorPayload | null,
): Partial<Record<'current_password' | 'password' | 'password_confirmation', string>> {
  const fields = payload?.error?.details?.fields;

  if (fields === undefined) {
    return {};
  }

  return {
    current_password: typeof fields.current_password?.[0] === 'string' ? fields.current_password[0] : '',
    password: typeof fields.password?.[0] === 'string' ? fields.password[0] : '',
    password_confirmation:
      typeof fields.password_confirmation?.[0] === 'string' ? fields.password_confirmation[0] : '',
  };
}

function extractPasswordResetFieldErrors(
  payload: ApiErrorPayload | null,
): Partial<Record<'email' | 'token' | 'password' | 'password_confirmation', string>> {
  const fields = payload?.error?.details?.fields;

  if (fields === undefined) {
    return {};
  }

  return {
    email: typeof fields.email?.[0] === 'string' ? fields.email[0] : '',
    token: typeof fields.token?.[0] === 'string' ? fields.token[0] : '',
    password: typeof fields.password?.[0] === 'string' ? fields.password[0] : '',
    password_confirmation:
      typeof fields.password_confirmation?.[0] === 'string' ? fields.password_confirmation[0] : '',
  };
}

function extractUpdateProfileFieldErrors(
  payload: ApiErrorPayload | null,
): Partial<Record<'name' | 'email' | 'avatar_path' | 'current_password' | 'diet' | 'allergies' | 'default_servings', string>> {
  const fields = payload?.error?.details?.fields;

  if (fields === undefined) {
    return {};
  }

  return {
    name: typeof fields.name?.[0] === 'string' ? fields.name[0] : '',
    email: typeof fields.email?.[0] === 'string' ? fields.email[0] : '',
    avatar_path: typeof fields.avatar_path?.[0] === 'string' ? fields.avatar_path[0] : '',
    current_password: typeof fields.current_password?.[0] === 'string' ? fields.current_password[0] : '',
    diet: typeof fields.diet?.[0] === 'string' ? fields.diet[0] : '',
    allergies: typeof fields.allergies?.[0] === 'string' ? fields.allergies[0] : '',
    default_servings: typeof fields.default_servings?.[0] === 'string' ? fields.default_servings[0] : '',
  };
}

function authHeaders(session: Pick<AuthSession, 'accessToken' | 'tokenType'>): HeadersInit {
  const headers: HeadersInit = {
    Accept: 'application/json',
  };

  if (session.accessToken !== '' && session.tokenType !== '') {
    headers.Authorization = `${session.tokenType} ${session.accessToken}`;
  }

  return headers;
}

export const useAuthStore = defineStore('auth', {
  state: () => {
    const session = readPersistedSession();

    return {
      accessToken: '',
      tokenType: '',
      expiresIn: 0,
      user: session?.user ?? null,
      pendingTwoFactor: readPendingTwoFactor(),
      status: (session === null ? 'idle' : 'restoring') as AuthStatus,
      isRestored: session === null,
    };
  },

  getters: {
    isAuthenticated: (state) =>
      state.isRestored && state.status === 'authenticated' && state.user !== null,
  },

  actions: {
    applySession(session: AuthSession): void {
      this.accessToken = session.accessToken;
      this.tokenType = session.tokenType;
      this.expiresIn = session.expiresIn;
      this.user = session.user;
      this.status = 'authenticated';
      this.isRestored = true;

      persistSession({
        user: session.user,
      });
    },

    clearSession(): void {
      this.accessToken = '';
      this.tokenType = '';
      this.expiresIn = 0;
      this.user = null;
      this.status = 'idle';
      this.isRestored = true;

      persistSession(null);
    },

    clearPendingTwoFactor(): void {
      this.pendingTwoFactor = null;
      persistPendingTwoFactor(null);
    },

    async refreshSession(): Promise<AuthSession | null> {
      if (this.accessToken === '' || this.tokenType === '') {
        return null;
      }

      try {
        const response = await apiFetch('/api/auth/refresh', {
          method: 'POST',
          headers: authHeaders({
            accessToken: this.accessToken,
            tokenType: this.tokenType,
          }),
        });

        const payload = (await response.json().catch(() => null)) as
          | LoginSuccessPayload
          | ApiErrorPayload
          | null;

        if (response.status === 401) {
          this.clearSession();

          return null;
        }

        if (!response.ok || payload?.success !== true) {
          return null;
        }

        return {
          accessToken: payload.data.access_token,
          tokenType: payload.data.token_type,
          expiresIn: payload.data.expires_in,
          user: payload.data.user,
        };
      } catch {
        return null;
      }
    },

    async fetchCurrentUser(): Promise<AuthUser | null> {
      try {
        const response = await apiFetch('/api/auth/me', {
          method: 'GET',
          credentials: 'include',
          headers: authHeaders({
            accessToken: this.accessToken,
            tokenType: this.tokenType,
          }),
        });

        const payload = (await response.json().catch(() => null)) as
          | CurrentUserSuccessPayload
          | ApiErrorPayload
          | null;

        if (response.status === 401) {
          this.clearSession();

          return null;
        }

        if (!response.ok || payload?.success !== true) {
          return null;
        }

        return payload.data;
      } catch {
        return null;
      }
    },

    async restoreSession(): Promise<void> {
      if (this.isRestored) {
        return;
      }

      if (this.accessToken === '' || this.tokenType === '') {
        this.status = 'restoring';
        const currentUser = await this.fetchCurrentUser();
        if (currentUser !== null) {
          this.user = currentUser;
          this.status = 'authenticated';
          this.isRestored = true;
          persistSession({ user: currentUser });
          return;
        }
        this.clearSession();
        return;
      }

      if (restoreSessionPromise !== null) {
        return restoreSessionPromise;
      }

      this.status = 'restoring';

      restoreSessionPromise = (async () => {
        const refreshedSession = await this.refreshSession();

        if (refreshedSession === null) {
          this.clearSession();

          return;
        }

        this.applySession(refreshedSession);

        const currentUser = await this.fetchCurrentUser();

        if (currentUser === null) {
          this.clearSession();

          return;
        }

        this.applySession({
          ...refreshedSession,
          user: currentUser,
        });
      })().finally(() => {
        restoreSessionPromise = null;
        this.isRestored = true;

        if (this.status === 'restoring') {
          this.status = this.user === null ? 'idle' : 'authenticated';
        }
      });

      return restoreSessionPromise;
    },

    async login(credentials: LoginCredentials): Promise<LoginResult> {
      this.isRestored = false;
      this.status = 'loading';

      try {
        const response = await apiFetch('/api/auth/login', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            email: credentials.email.trim().toLowerCase(),
            password: credentials.password,
          }),
        });

        const payload = (await response.json().catch(() => null)) as
          | LoginSuccessPayload
          | TwoFactorRequiredPayload
          | ApiErrorPayload
          | null;

        if (response.status === 202 && payload?.success === true && 'two_factor_required' in payload.data) {
          const twoFactorPayload = payload as TwoFactorRequiredPayload;
          this.clearSession();
          this.pendingTwoFactor = {
            challenge: twoFactorPayload.data.challenge,
            expiresIn: twoFactorPayload.data.expires_in,
            email: credentials.email.trim().toLowerCase(),
          };
          persistPendingTwoFactor(this.pendingTwoFactor);
          return { ok: true, twoFactorRequired: true };
        }

        if (response.ok && payload?.success === true && 'access_token' in payload.data) {
          this.applySession({
            accessToken: payload.data.access_token,
            tokenType: payload.data.token_type,
            expiresIn: payload.data.expires_in,
            user: payload.data.user,
          });

          return {
            ok: true,
          };
        }

        this.clearSession();

        const errorPayload = payload?.success === false ? payload : null;

        return {
          ok: false,
          message: errorPayload?.error?.message ?? 'Une erreur est survenue pendant la connexion.',
          fieldErrors: extractFieldErrors(errorPayload),
        };
      } catch {
        this.clearSession();

        return {
          ok: false,
          message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }
    },

    async verifyTwoFactor(code: string): Promise<VerifyTwoFactorResult> {
      const challenge = this.pendingTwoFactor;
      if (challenge === null) return { ok: false, message: 'Votre demande de connexion a expire. Recommencez la connexion.' };

      this.status = 'loading';
      try {
        const response = await apiFetch('/api/auth/2fa/verify', {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ challenge: challenge.challenge, code }),
        });
        const payload = (await response.json().catch(() => null)) as LoginSuccessPayload | ApiErrorPayload | null;
        if (response.ok && payload?.success === true && 'access_token' in payload.data) {
          this.clearPendingTwoFactor();
          this.applySession({ accessToken: payload.data.access_token, tokenType: payload.data.token_type, expiresIn: payload.data.expires_in, user: payload.data.user });
          return { ok: true };
        }
        this.status = 'idle';
        return { ok: false, message: payload?.success === false ? payload.error?.message ?? 'Le code est invalide ou expire.' : 'Le code est invalide ou expire.' };
      } catch {
        this.status = 'idle';
        return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
      }
    },

    async setTwoFactorEnabled(enabled: boolean, currentPassword = ''): Promise<TwoFactorActionResult> {
      if (this.user === null || this.accessToken === '' || this.tokenType === '') return { ok: false, message: 'Votre session a expire. Reconnectez-vous.' };
      this.status = 'loading';
      try {
        const response = await apiFetch(`/api/auth/2fa/${enabled ? 'enable' : 'disable'}`, {
          method: 'POST',
          headers: { ...authHeaders({ accessToken: this.accessToken, tokenType: this.tokenType }), 'Content-Type': 'application/json' },
          ...(enabled ? {} : { body: JSON.stringify({ current_password: currentPassword }) }),
        });
        const payload = (await response.json().catch(() => null)) as { success: true; data?: { enabled?: boolean } } | ApiErrorPayload | null;
        this.status = 'authenticated';
        if (response.ok && payload?.success === true) {
          this.user = { ...this.user, two_factor_enabled: enabled };
          persistSession({ user: this.user });
          return { ok: true, enabled };
        }
        return { ok: false, message: payload?.success === false ? payload.error?.message ?? 'Impossible de modifier la verification en deux etapes.' : 'Impossible de modifier la verification en deux etapes.' };
      } catch {
        this.status = 'authenticated';
        return { ok: false, message: 'Impossible de joindre le serveur. Reessayez dans un instant.' };
      }
    },

    async resendEmailVerification(): Promise<ResendEmailVerificationResult> {
      if (this.user === null) {
        return { ok: false, message: 'Une authentification est requise.' };
      }

      this.status = 'loading';

      try {
        const response = await apiFetch('/api/auth/email/verification-notification', {
          method: 'POST',
          headers: authHeaders({ accessToken: this.accessToken, tokenType: this.tokenType }),
        });
        const payload = (await response.json().catch(() => null)) as
          | { success: true; data?: { message?: string } }
          | ApiErrorPayload
          | null;

        if (response.status === 401) {
          this.clearSession();
          return { ok: false, message: 'Votre session a expire. Reconnectez-vous.' };
        }

        this.status = 'authenticated';

        if (response.ok && payload?.success === true) {
          return { ok: true, message: payload.data?.message ?? 'Un email de vérification a été envoyé.' };
        }

        return {
          ok: false,
          message:
            (payload !== null && 'error' in payload ? payload.error?.message : undefined) ??
            'Impossible de renvoyer l’email de vérification.',
        };
      } catch {
        this.status = 'authenticated';
        return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
      }
    },

    async verifyEmail(userId: string, token: string): Promise<EmailVerificationResult> {
      try {
        const response = await apiFetch(`/api/auth/email/verify/${encodeURIComponent(userId)}/${encodeURIComponent(token)}`, {
          method: 'GET',
          headers: { Accept: 'application/json' },
        });
        const payload = (await response.json().catch(() => null)) as
          | { success: true; data?: { user?: AuthUser } }
          | ApiErrorPayload
          | null;

        if (!response.ok || payload?.success !== true) {
          return {
            ok: false,
            message:
              (payload !== null && 'error' in payload ? payload.error?.message : undefined) ??
              'Le lien de vérification est invalide ou expiré.',
          };
        }

        const verifiedUser = payload.data?.user ?? null;
        if (verifiedUser !== null && this.user?.id === verifiedUser.id) {
          this.user = verifiedUser;
          persistSession({ user: verifiedUser });
        }

        return { ok: true, user: verifiedUser };
      } catch {
        return { ok: false, message: 'Impossible de joindre le serveur. Réessayez dans un instant.' };
      }
    },

    async completeOAuthLogin(params: URLSearchParams, provider: 'Google' | 'Microsoft' | 'OAuth'): Promise<LoginResult> {
      const error = params.get('error_description') || params.get('oauth_error') || params.get('error');

      if (error !== null && error !== '') {
        this.clearSession();
        return { ok: false, message: error, fieldErrors: {} };
      }

      if (!params.has('access_token') && !params.has('token')) {
      this.status = 'loading';
      this.isRestored = false;
      const cookieUser = await this.fetchCurrentUser();
      if (cookieUser === null) {
        this.clearSession();
        return { ok: false, message: `Impossible de finaliser la connexion avec ${provider}.`, fieldErrors: {} };
      }

      this.applySession({ accessToken: '', tokenType: 'Bearer', expiresIn: 3600, user: cookieUser });
      return { ok: true };
      }

      if (params.has('access_token') || params.has('token')) {
      const accessToken = params.get('access_token') || params.get('token');

      if (accessToken === null || accessToken === '') {
        this.clearSession();
        return {
          ok: false,
          message: 'La réponse de Google est incomplète. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }

      this.status = 'loading';
      this.isRestored = false;

      const tokenType = params.get('token_type') || 'Bearer';
      const expiresInValue = Number(params.get('expires_in'));
      const expiresIn = Number.isFinite(expiresInValue) && expiresInValue > 0 ? expiresInValue : 3600;
      let user: AuthUser | null = null;

      try {
        const rawUser = params.get('user');
        user = rawUser === null ? null : (JSON.parse(rawUser) as AuthUser);
      } catch {
        user = null;
      }

      if (
        user === null ||
        typeof user.id !== 'number' ||
        typeof user.email !== 'string' ||
        typeof user.name !== 'string'
      ) {
        this.accessToken = accessToken;
        this.tokenType = tokenType;
        const fetchedUser = await this.fetchCurrentUser();
        user = fetchedUser;
      }

      if (user === null) {
        this.clearSession();
        return { ok: false, message: `Impossible de finaliser la connexion avec ${provider}.`, fieldErrors: {} };
      }

      this.applySession({ accessToken, tokenType, expiresIn, user });
      return { ok: true };
      }

      this.clearSession();
      return { ok: false, message: `Impossible de finaliser la connexion avec ${provider}.`, fieldErrors: {} };
    },

    async completeGoogleLogin(params: URLSearchParams): Promise<LoginResult> {
      return this.completeOAuthLogin(params, 'Google');
    },

    async completeMicrosoftLogin(params: URLSearchParams): Promise<LoginResult> {
      return this.completeOAuthLogin(params, 'Microsoft');
    },

    async logout(): Promise<void> {
      try {
        await apiFetch('/api/auth/logout', {
          method: 'POST',
          credentials: 'include',
          headers: authHeaders({ accessToken: this.accessToken, tokenType: this.tokenType }),
        });
      } finally {
        this.clearSession();
      }
    },

    async changePassword(
      currentPassword: string,
      password: string,
      passwordConfirmation: string,
    ): Promise<ChangePasswordResult> {
      if (this.user === null) {
        return {
          ok: false,
          message: 'Une authentification est requise.',
          fieldErrors: {},
        };
      }

      this.status = 'loading';

      try {
        const response = await apiFetch('/api/auth/password', {
          method: 'PUT',
          headers: {
            ...authHeaders({
              accessToken: this.accessToken,
              tokenType: this.tokenType,
            }),
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            current_password: currentPassword,
            password,
            password_confirmation: passwordConfirmation,
          }),
        });

        const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

        if (response.status === 401) {
          this.clearSession();
        }

        if (response.ok && payload?.success !== false) {
          this.clearSession();

          return { ok: true };
        }

        if (this.status === 'loading') {
          this.status = 'authenticated';
        }

        return {
          ok: false,
          message: payload?.error?.message ?? 'Une erreur est survenue pendant la modification du mot de passe.',
          fieldErrors: extractChangePasswordFieldErrors(payload),
        };
      } catch {
        this.status = 'authenticated';

        return {
          ok: false,
          message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }
    },

    async requestPasswordReset(email: string): Promise<RequestPasswordResetResult> {
      this.status = 'loading';

      try {
        const response = await apiFetch('/api/auth/password/email', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ email: email.trim().toLowerCase() }),
        });

        const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

        if (response.ok && payload?.success !== false) {
          this.status = this.user === null ? 'idle' : 'authenticated';
          return { ok: true };
        }

        this.status = this.user === null ? 'idle' : 'authenticated';

        return {
          ok: false,
          message: payload?.error?.message ?? 'Une erreur est survenue pendant la demande de réinitialisation.',
          fieldErrors: extractPasswordResetFieldErrors(payload),
        };
      } catch {
        this.status = this.user === null ? 'idle' : 'authenticated';
        return {
          ok: false,
          message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }
    },

    async resetPassword(
      email: string,
      token: string,
      password: string,
      passwordConfirmation: string,
    ): Promise<ResetPasswordResult> {
      this.status = 'loading';

      try {
        const response = await apiFetch('/api/auth/password/reset', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            email: email.trim().toLowerCase(),
            token: token.trim(),
            password,
            password_confirmation: passwordConfirmation,
          }),
        });

        const payload = (await response.json().catch(() => null)) as ApiErrorPayload | null;

        if (response.ok && payload?.success !== false) {
          this.status = this.user === null ? 'idle' : 'authenticated';
          return { ok: true };
        }

        this.status = this.user === null ? 'idle' : 'authenticated';

        return {
          ok: false,
          message: payload?.error?.message ?? 'Une erreur est survenue pendant la réinitialisation du mot de passe.',
          fieldErrors: extractPasswordResetFieldErrors(payload),
        };
      } catch {
        this.status = this.user === null ? 'idle' : 'authenticated';
        return {
          ok: false,
          message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }
    },

    async updateProfile(
      name: string,
      email: string,
      avatar: File | null,
      currentPassword: string,
      originalEmail: string,
      diet: string | null,
      allergies: string[],
      defaultServings: number,
    ): Promise<UpdateProfileResult> {
      if (this.user === null) {
        return {
          ok: false,
          message: 'Une authentification est requise.',
          fieldErrors: {},
        };
      }

      this.status = 'loading';
      const normalizedEmail = email.trim().toLowerCase();
      const body = new FormData();
      body.append('_method', 'PATCH');
      body.append('name', name.trim());

      if (avatar !== null) body.append('avatar', avatar);

      if (normalizedEmail !== originalEmail) {
        body.append('email', normalizedEmail);
        body.append('current_password', currentPassword);
      }

      body.append('diet', diet ?? '');
      allergies.forEach((allergy) => body.append('allergies[]', allergy));
      if (allergies.length === 0) body.append('allergies[]', '');
      body.append('default_servings', String(defaultServings));

      try {
        const response = await apiFetch('/api/auth/me', {
          method: 'POST',
          headers: {
            ...authHeaders({
              accessToken: this.accessToken,
              tokenType: this.tokenType,
            }),
          },
          body,
        });

        const payload = (await response.json().catch(() => null)) as
          | { success: true; data: AuthUser }
          | ApiErrorPayload
          | null;

        if (response.status === 401) {
          this.clearSession();
        }

        if (response.ok && payload?.success === true) {
          this.user = payload.data;
          this.status = 'authenticated';
          persistSession({
            user: payload.data,
          });

          return { ok: true };
        }

        if (this.status === 'loading') {
          this.status = 'authenticated';
        }

        const errorPayload = payload?.success === false ? payload : null;

        return {
          ok: false,
          message: errorPayload?.error?.message ?? 'Une erreur est survenue pendant la modification du profil.',
          fieldErrors: extractUpdateProfileFieldErrors(errorPayload),
        };
      } catch {
        this.status = 'authenticated';

        return {
          ok: false,
          message: 'Impossible de joindre le serveur. Reessayez dans un instant.',
          fieldErrors: {},
        };
      }
    },
  },
});
