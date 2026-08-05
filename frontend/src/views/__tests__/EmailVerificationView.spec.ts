import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EmailVerificationView from '../EmailVerificationView.vue';
import { useAuthStore } from '@/stores/auth';

const routeParams: Record<string, string> = { id: '7', token: 'valid-token' };

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: routeParams }),
  RouterLink: { template: '<a><slot /></a>' },
}));

describe('EmailVerificationView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    setActivePinia(createPinia());
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    routeParams.id = '7';
    routeParams.token = 'valid-token';
  });

  it('shows the pending state before returning a verification result', async () => {
    let resolveRequest: ((value: Response | PromiseLike<Response>) => void) | undefined;
    fetchMock.mockImplementation(() => new Promise<Response>((resolve) => { resolveRequest = resolve; }));

    const wrapper = mount(EmailVerificationView);
    expect(wrapper.text()).toContain('Vérification en cours');

    resolveRequest?.({
      ok: true,
      json: async () => ({ success: true, data: { user: { id: 7, name: 'Jane', email: 'jane@example.com', email_verified: true } } }),
    } as Response);
    await flushPromises();

    expect(wrapper.text()).toContain('Adresse vérifiée');
  });

  it('shows the waiting message and resend action without a verification token', async () => {
    delete routeParams.id;
    delete routeParams.token;
    const authStore = useAuthStore();
    authStore.applySession({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7,
        name: 'Jane',
        email: 'jane@example.com',
        email_verified: false,
        avatar_path: null,
        last_login_at: null,
        created_at: null,
      },
    });
    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 202,
      json: async () => ({ success: true, data: { message: 'Email renvoyé.' } }),
    } as Response);

    const wrapper = mount(EmailVerificationView);
    await flushPromises();
    expect(wrapper.text()).toContain('Vérifiez votre adresse email');
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Email renvoyé.');
  });

  it('shows backend errors and allows a signed-in unverified user to resend', async () => {
    const authStore = useAuthStore();
    authStore.applySession({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7,
        name: 'Jane',
        email: 'jane@example.com',
        email_verified: false,
        avatar_path: null,
        last_login_at: null,
        created_at: null,
      },
    });
    fetchMock
      .mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => ({ success: false, error: { code: 'email_verification_invalid', message: 'Le lien est expiré.' } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 202,
        json: async () => ({ success: true, data: { message: 'Un email a été envoyé.' } }),
      } as Response);

    const wrapper = mount(EmailVerificationView);
    await flushPromises();
    expect(wrapper.text()).toContain('Le lien est expiré.');

    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/auth/email/verification-notification', expect.objectContaining({ method: 'POST' }));
    expect(wrapper.text()).toContain('Un email a été envoyé.');
  });
});
