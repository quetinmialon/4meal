import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';

import { createAppRouter, installAuthGuard } from '../index';

describe('auth navigation guards', () => {
  beforeEach(() => {
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  it('redirects unauthenticated users away from private routes after session restoration', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createAppRouter(createMemoryHistory());
    installAuthGuard(router, pinia);

    const authStore = useAuthStore(pinia);
    const restoreSpy = vi.spyOn(authStore, 'restoreSession').mockResolvedValue();

    await router.push({ name: 'dashboard' });
    await router.isReady();

    expect(restoreSpy).toHaveBeenCalled();
    expect(router.currentRoute.value.name).toBe('login');
  });

  it('redirects authenticated users away from guest-only routes', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createAppRouter(createMemoryHistory());
    installAuthGuard(router, pinia);

    const authStore = useAuthStore(pinia);
    authStore.applySession({
      accessToken: 'valid-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7,
        name: 'Jane Doe',
        email: 'jane.doe@example.com',
        avatar_path: null,
        last_login_at: null,
        created_at: '2026-07-17T10:00:00Z',
      },
    });

    const restoreSpy = vi.spyOn(authStore, 'restoreSession').mockResolvedValue();

    await router.push({ name: 'login' });
    await router.isReady();

    expect(restoreSpy).toHaveBeenCalled();
    expect(router.currentRoute.value.name).toBe('dashboard');
  });
});
