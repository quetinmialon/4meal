import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import LoginView from '../LoginView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
}));

describe('LoginView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    pushMock.mockReset();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  it('submits the form, shows a loading state and redirects to the dashboard after success', async () => {
    let resolveRequest: ((value: Response | PromiseLike<Response>) => void) | undefined;

    fetchMock.mockImplementation(
      () =>
        new Promise<Response>((resolve) => {
          resolveRequest = resolve;
        }),
    );

    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    await wrapper.get('#email-input').setValue(' Jane.DOE@example.com ');
    await wrapper.get('#password-input').setValue('password123');

    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(wrapper.get('button[type="submit"]').text()).toBe('Connexion...');
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();

    resolveRequest?.({
      ok: true,
      json: async () => ({
        success: true,
        data: {
          access_token: 'jwt-token',
          token_type: 'Bearer',
          expires_in: 3600,
          user: {
            id: 7,
            name: 'Jane Doe',
            email: 'jane.doe@example.com',
            created_at: '2026-07-17T10:00:00Z',
          },
        },
      }),
    } as Response);

    await flushPromises();

    const authStore = useAuthStore();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/login', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email: 'jane.doe@example.com',
        password: 'password123',
      }),
    });

    expect(authStore.isAuthenticated).toBe(true);
    expect(authStore.user).toEqual({
      id: 7,
      name: 'Jane Doe',
      email: 'jane.doe@example.com',
      created_at: '2026-07-17T10:00:00Z',
    });
    expect(pushMock).toHaveBeenCalledWith({
      name: 'dashboard',
    });
  });

  it('renders backend authentication errors and keeps the user logged out after failure', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      json: async () => ({
        success: false,
        error: {
          code: 'authentication_error',
          message: 'Identifiants invalides.',
        },
      }),
    } as Response);

    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    await wrapper.get('#email-input').setValue('jane@example.com');
    await wrapper.get('#password-input').setValue('wrong-password');

    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const authStore = useAuthStore();

    expect(wrapper.get('[role="alert"]').text()).toContain('Identifiants invalides.');
    expect(authStore.isAuthenticated).toBe(false);
    expect(authStore.user).toBeNull();
    expect(pushMock).not.toHaveBeenCalled();
  });
});
