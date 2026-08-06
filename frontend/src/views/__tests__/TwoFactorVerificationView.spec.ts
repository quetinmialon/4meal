import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';
import TwoFactorVerificationView from '../TwoFactorVerificationView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRouter: () => ({ push: pushMock }),
}));

describe('TwoFactorVerificationView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.sessionStorage.clear();
    setActivePinia(createPinia());
  });

  it('validates the six digit code and completes the session', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { access_token: 'jwt', token_type: 'Bearer', expires_in: 900, user: { id: 7, name: 'Jane', email: 'jane@example.com', created_at: null } } }),
    } as Response);
    const pinia = createPinia();
    setActivePinia(pinia);
    const authStore = useAuthStore(pinia);
    authStore.pendingTwoFactor = { challenge: 'b'.repeat(64), expiresIn: 600, email: 'jane@example.com' };

    const wrapper = mount(TwoFactorVerificationView, { global: { plugins: [pinia] } });
    await wrapper.get('#two-factor-code').setValue('123');
    await wrapper.get('form').trigger('submit.prevent');
    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('[role="alert"]').text()).toContain('6 chiffres');

    await wrapper.get('#two-factor-code').setValue('123456');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();
    expect(fetchMock).toHaveBeenCalledWith('/api/auth/2fa/verify', expect.objectContaining({ method: 'POST' }));
    expect(useAuthStore(pinia).isAuthenticated).toBe(true);
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('shows backend errors and keeps the challenge recoverable', async () => {
    fetchMock.mockResolvedValue({ ok: false, status: 401, json: async () => ({ success: false, error: { message: 'Code invalide ou expiré.' } }) } as Response);
    const pinia = createPinia();
    setActivePinia(pinia);
    const authStore = useAuthStore(pinia);
    authStore.pendingTwoFactor = { challenge: 'c'.repeat(64), expiresIn: 600, email: 'jane@example.com' };
    const wrapper = mount(TwoFactorVerificationView, { global: { plugins: [pinia] } });
    await wrapper.get('#two-factor-code').setValue('123456');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('Code invalide');
    expect(useAuthStore(pinia).pendingTwoFactor?.challenge).toBe('c'.repeat(64));
  });
});
