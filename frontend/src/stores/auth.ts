import { defineStore } from 'pinia';

const AUTH_STORAGE_KEY = '4meal.auth.session';

type AuthStatus = 'idle' | 'loading' | 'authenticated';

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

type PersistedSession = {
  accessToken: string;
  tokenType: string;
  expiresIn: number;
  user: AuthUser;
};

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

export const useAuthStore = defineStore('auth', {
  state: () => {
    const session = readPersistedSession();

    return {
      accessToken: session?.accessToken ?? '',
      tokenType: session?.tokenType ?? '',
      expiresIn: session?.expiresIn ?? 0,
      user: session?.user ?? null,
      status: (session === null ? 'idle' : 'authenticated') as AuthStatus,
    };
  },

  getters: {
    isAuthenticated: (state) => state.user !== null && state.accessToken !== '',
  },

  actions: {
    applySession(session: AuthSession): void {
      this.accessToken = session.accessToken;
      this.tokenType = session.tokenType;
      this.expiresIn = session.expiresIn;
      this.user = session.user;
      this.status = 'authenticated';

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

      persistSession(null);
    },

    async login(credentials: LoginCredentials): Promise<LoginResult> {
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
  },
});
