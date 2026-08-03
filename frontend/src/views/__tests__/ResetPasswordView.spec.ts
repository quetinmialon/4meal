import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ResetPasswordView from '../ResetPasswordView.vue';

const query = { email: 'jane@example.com', token: 'temporary-token' };

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ query }),
}));

describe('ResetPasswordView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    setActivePinia(createPinia());
  });

  function mountView() {
    return mount(ResetPasswordView, { global: { plugins: [createPinia()] } });
  }

  it('prefills the reset data, submits the new password and shows success', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { message: 'ok' } }),
    } as Response);

    const wrapper = mountView();
    expect((wrapper.get('#email-input').element as HTMLInputElement).value).toBe('jane@example.com');
    expect((wrapper.get('#token-input').element as HTMLInputElement).value).toBe('temporary-token');

    await wrapper.get('#password-input').setValue('new-password123');
    await wrapper.get('#passwordConfirmation-input').setValue('new-password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/password/reset', {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: 'jane@example.com',
        token: 'temporary-token',
        password: 'new-password123',
        password_confirmation: 'new-password123',
      }),
    });
    expect(wrapper.get('[role="status"]').text()).toContain('réinitialisé');
  });

  it('validates password confirmation before calling the API', async () => {
    const wrapper = mountView();
    await wrapper.get('#password-input').setValue('new-password123');
    await wrapper.get('#passwordConfirmation-input').setValue('different-password');
    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('#passwordConfirmation-error').text()).toContain('correspondent');
  });

  it('renders an invalid or expired token error', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: {
          message: 'Les données fournies sont invalides.',
          details: { fields: { token: ['Le code est invalide ou expiré.'] } },
        },
      }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#password-input').setValue('new-password123');
    await wrapper.get('#passwordConfirmation-input').setValue('new-password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('#token-error').text()).toContain('invalide ou expiré');
    expect(wrapper.get('[role="alert"]').text()).toContain('données fournies');
  });
});
