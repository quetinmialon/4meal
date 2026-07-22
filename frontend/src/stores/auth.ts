import { defineStore } from 'pinia';

const AUTH_STORAGE_KEY = '4meal.auth.session';

type AuthStatus = 'idle' | 'loading' | 'restoring' | 'authenticated';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  created_at: string | null;
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
    };
  };
};

export type LoginResult =
  | {
      ok: true;
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

type PersistedSession = {
  accessToken: string;
  tokenType: string;
  expiresIn: number;
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

    if (
      typeof parsed.accessToken !== 'string' ||
      typeof parsed.tokenType !== 'string' ||
      typeof parsed.expiresIn !== 'number' ||
      !isAuthUser(parsed.user)
    ) {
      return null;
    }

    return {
      accessToken: parsed.accessToken,
      tokenType: parsed.tokenType,
      expiresIn: parsed.expiresIn,
      user: parsed.user,
    };
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

  window.localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));
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

function authHeaders(session: Pick<AuthSession, 'accessToken' | 'tokenType'>): HeadersInit {
  return {
    Accept: 'application/json',
    Authorization: `${session.tokenType} ${session.accessToken}`,
  };
}

export const useAuthStore = defineStore('auth', {
  state: () => {
    const session = readPersistedSession();

    return {
      accessToken: session?.accessToken ?? '',
      tokenType: session?.tokenType ?? '',
      expiresIn: session?.expiresIn ?? 0,
      user: session?.user ?? null,
      status: (session === null ? 'idle' : 'restoring') as AuthStatus,
      isRestored: session === null,
    };
  },

  getters: {
    isAuthenticated: (state) =>
      state.isRestored && state.status === 'authenticated' && state.user !== null && state.accessToken !== '',
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
        accessToken: session.accessToken,
        tokenType: session.tokenType,
        expiresIn: session.expiresIn,
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

    async refreshSession(): Promise<AuthSession | null> {
      if (this.accessToken === '' || this.tokenType === '') {
        return null;
      }

      try {
        const response = await fetch('/api/auth/refresh', {
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
      if (this.accessToken === '' || this.tokenType === '') {
        return null;
      }

      try {
        const response = await fetch('/api/auth/me', {
          method: 'GET',
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
        const response = await fetch('/api/auth/login', {
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
          | ApiErrorPayload
          | null;

        if (response.ok && payload?.success === true) {
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

    async completeOAuthLogin(params: URLSearchParams, provider: 'Google' | 'Microsoft' | 'OAuth'): Promise<LoginResult> {
      const error = params.get('error_description') || params.get('oauth_error') || params.get('error');

      if (error !== null && error !== '') {
        this.clearSession();
        return { ok: false, message: error, fieldErrors: {} };
      }

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

      if (user === null || typeof user.id !== 'number' || typeof user.email !== 'string' || typeof user.name !== 'string') {
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
    },

    async completeGoogleLogin(params: URLSearchParams): Promise<LoginResult> {
      return this.completeOAuthLogin(params, 'Google');
    },

    async completeMicrosoftLogin(params: URLSearchParams): Promise<LoginResult> {
      return this.completeOAuthLogin(params, 'Microsoft');
    },

    async changePassword(
      currentPassword: string,
      password: string,
      passwordConfirmation: string,
    ): Promise<ChangePasswordResult> {
      if (this.accessToken === '' || this.tokenType === '') {
        return {
          ok: false,
          message: 'Une authentification est requise.',
          fieldErrors: {},
        };
      }

      this.status = 'loading';

      try {
        const response = await fetch('/api/auth/password', {
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
  },
});
