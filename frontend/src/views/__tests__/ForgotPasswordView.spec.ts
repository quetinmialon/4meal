import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ForgotPasswordView from '../ForgotPasswordView.vue';

describe('ForgotPasswordView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    setActivePinia(createPinia());
  });

  function mountView() {
    return mount(ForgotPasswordView, {
      global: {
        plugins: [createPinia()],
        stubs: { RouterLink: { template: '<a><slot /></a>' } },
      },
    });
  }

  it('submits the email and displays a generic success message', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 202,
      json: async () => ({ success: true, data: { message: 'ok' } }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#email-input').setValue(' Jane.DOE@example.com ');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/password/email', {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: 'jane.doe@example.com' }),
    });
    expect(wrapper.get('[role="status"]').text()).toContain('Si cette adresse correspond à un compte');
  });

  it('validates the email before calling the API', async () => {
    const wrapper = mountView();
    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('#email-error').text()).toContain('requise');
    expect(wrapper.get('#email-input').attributes('aria-describedby')).toBe('email-error');
  });

  it('renders backend errors accessibly', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: { message: 'Données invalides.', details: { fields: { email: ['Adresse invalide.'] } } },
      }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#email-input').setValue('jane@example.com');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('#email-error').text()).toContain('invalide');
    expect(wrapper.get('[role="alert"]').text()).toContain('Données invalides');
  });
});
