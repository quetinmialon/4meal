import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '../auth';

describe('auth store session restoration', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('restores a persisted session through the HttpOnly cookie', async () => {
    window.localStorage.setItem(
      '4meal.auth.session',
      JSON.stringify({
        user: {
          id: 7,
          name: 'Cached User',
          email: 'cached@example.com',
          created_at: '2026-07-17T10:00:00Z',
        },
      }),
    );

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          success: true,
          data: {
            id: 7,
            name: 'Jane Doe',
            email: 'jane.doe@example.com',
            created_at: '2026-07-17T10:00:00Z',
          },
        }),
      } as Response);

    const authStore = useAuthStore();

    await authStore.restoreSession();

    expect(fetchMock).toHaveBeenNthCalledWith(1, '/api/auth/me', {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });

    expect(authStore.isAuthenticated).toBe(true);
    expect(authStore.accessToken).toBe('');
    expect(authStore.expiresIn).toBe(0);
    expect(authStore.user).toEqual({
      id: 7,
      name: 'Jane Doe',
      email: 'jane.doe@example.com',
      created_at: '2026-07-17T10:00:00Z',
    });

    expect(JSON.parse(window.localStorage.getItem('4meal.auth.session') ?? 'null')).toEqual({
      user: {
        id: 7,
        name: 'Jane Doe',
        email: 'jane.doe@example.com',
        created_at: '2026-07-17T10:00:00Z',
      },
    });
  });

  it('clears the persisted session when the backend returns 401', async () => {
    window.localStorage.setItem(
      '4meal.auth.session',
      JSON.stringify({
        user: {
          id: 7,
          name: 'Cached User',
          email: 'cached@example.com',
          created_at: '2026-07-17T10:00:00Z',
        },
      }),
    );

    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 401,
      json: async () => ({
        success: false,
        error: {
          code: 'authentication_error',
          message: 'Une authentification est requise.',
        },
      }),
    } as Response);

    const authStore = useAuthStore();

    await authStore.restoreSession();

    expect(authStore.isAuthenticated).toBe(false);
    expect(authStore.accessToken).toBe('');
    expect(authStore.user).toBeNull();
    expect(window.localStorage.getItem('4meal.auth.session')).toBeNull();
  });

  it('activates 2FA and maps backend errors when disabling it', async () => {
    const authStore = useAuthStore();
    authStore.applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ success: true, data: { enabled: true } }) } as Response);

    await expect(authStore.setTwoFactorEnabled(true)).resolves.toEqual({ ok: true, enabled: true });
    expect(fetchMock).toHaveBeenCalledWith('/api/auth/2fa/enable', expect.objectContaining({ method: 'POST' }));
    expect(authStore.user?.two_factor_enabled).toBe(true);

    fetchMock.mockResolvedValueOnce({ ok: false, status: 422, json: async () => ({ success: false, error: { message: 'Mot de passe incorrect.' } }) } as Response);
    await expect(authStore.setTwoFactorEnabled(false, 'wrong')).resolves.toEqual({ ok: false, message: 'Mot de passe incorrect.' });
    expect(authStore.user?.two_factor_enabled).toBe(true);
  });
});
