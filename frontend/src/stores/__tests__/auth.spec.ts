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

  it('restores a persisted session by refreshing the token and fetching the current user', async () => {
    window.localStorage.setItem(
      '4meal.auth.session',
      JSON.stringify({
        accessToken: 'stored-token',
        tokenType: 'Bearer',
        expiresIn: 900,
        user: {
          id: 7,
          name: 'Cached User',
          email: 'cached@example.com',
          created_at: '2026-07-17T10:00:00Z',
        },
      }),
    );

    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          success: true,
          data: {
            access_token: 'renewed-token',
            token_type: 'Bearer',
            expires_in: 1200,
            user: {
              id: 7,
              name: 'Jane Doe',
              email: 'jane.doe@example.com',
              created_at: '2026-07-17T10:00:00Z',
            },
          },
        }),
      } as Response)
      .mockResolvedValueOnce({
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

    expect(fetchMock).toHaveBeenNthCalledWith(1, '/api/auth/refresh', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer stored-token',
      },
    });

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/auth/me', {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer renewed-token',
      },
    });

    expect(authStore.isAuthenticated).toBe(true);
    expect(authStore.accessToken).toBe('renewed-token');
    expect(authStore.expiresIn).toBe(1200);
    expect(authStore.user).toEqual({
      id: 7,
      name: 'Jane Doe',
      email: 'jane.doe@example.com',
      created_at: '2026-07-17T10:00:00Z',
    });

    expect(JSON.parse(window.localStorage.getItem('4meal.auth.session') ?? 'null')).toEqual({
      accessToken: 'renewed-token',
      tokenType: 'Bearer',
      expiresIn: 1200,
      user: {
        id: 7,
        name: 'Jane Doe',
        email: 'jane.doe@example.com',
        created_at: '2026-07-17T10:00:00Z',
      },
    });
  });

  it('clears the persisted session when the backend returns 401 during token renewal', async () => {
    window.localStorage.setItem(
      '4meal.auth.session',
      JSON.stringify({
        accessToken: 'stored-token',
        tokenType: 'Bearer',
        expiresIn: 900,
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
});
