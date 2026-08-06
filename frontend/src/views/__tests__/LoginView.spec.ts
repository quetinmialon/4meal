import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import LoginView from '../LoginView.vue';

const pushMock = vi.fn();
const replaceMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRouter: () => ({
    push: pushMock,
    replace: replaceMock,
  }),
}));

describe('LoginView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    pushMock.mockReset();
    replaceMock.mockReset();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    window.history.replaceState({}, '', '/connexion');
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
      credentials: 'include',
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

  it('redirects to the email 2FA screen and preserves the challenge', async () => {
    fetchMock.mockResolvedValue({
      status: 202,
      ok: true,
      json: async () => ({ success: true, data: { two_factor_required: true, challenge: 'a'.repeat(64), expires_in: 600 } }),
    } as Response);

    const wrapper = mount(LoginView, { global: { plugins: [createPinia()] } });
    await wrapper.get('#email-input').setValue('jane@example.com');
    await wrapper.get('#password-input').setValue('password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(pushMock).toHaveBeenCalledWith({ name: 'two-factor-verification' });
    expect(JSON.parse(window.sessionStorage.getItem('4meal.auth.two-factor') ?? 'null')).toMatchObject({
      challenge: 'a'.repeat(64),
      email: 'jane@example.com',
    });
  });

  it('starts the Google OAuth flow through the backend endpoint', () => {
    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    const googleButton = wrapper.get('a[aria-label="Continuer avec Google"]');

    expect(googleButton.attributes('href')).toBe('/api/auth/google/redirect');
  });

  it('starts the Microsoft OAuth flow through the backend endpoint', () => {
    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    const microsoftButton = wrapper.get('a[aria-label="Continuer avec Microsoft"]');

    expect(microsoftButton.attributes('href')).toBe('/api/auth/microsoft/redirect');
  });

  it('displays an OAuth error returned by Google', async () => {
    window.history.replaceState({}, '', '/connexion?error=access_denied');

    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('access_denied');
    expect(replaceMock).toHaveBeenCalledWith({ query: {} });
    expect(pushMock).not.toHaveBeenCalled();
  });

  it('displays an OAuth error returned by Microsoft', async () => {
    window.history.replaceState({}, '', '/connexion?oauth_error=La%20connexion%20Microsoft%20a%20%C3%A9chou%C3%A9.');

    const wrapper = mount(LoginView, {
      global: {
        plugins: [createPinia()],
      },
    });

    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('La connexion Microsoft a échoué.');
    expect(replaceMock).toHaveBeenCalledWith({ query: {} });
    expect(pushMock).not.toHaveBeenCalled();
  });

  it('restores the OAuth session from the secure cookie without reading a token from the URL', async () => {
    window.history.replaceState({}, '', '/connexion?oauth=success');
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
      }),
    } as Response);

    mount(LoginView, { global: { plugins: [createPinia()] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/me', {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
    expect(window.location.search).toBe('?oauth=success');
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
    expect(useAuthStore().accessToken).toBe('');
  });
});
