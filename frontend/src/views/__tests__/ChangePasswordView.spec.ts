import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ChangePasswordView from '../ChangePasswordView.vue';

describe('ChangePasswordView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  function mountView() {
    const pinia = createPinia();
    const authStore = useAuthStore(pinia);

    authStore.applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7,
        name: 'Jane Doe',
        email: 'jane@example.com',
        avatar_path: null,
        last_login_at: null,
        created_at: null,
      },
    });

    return mount(ChangePasswordView, {
      global: {
        plugins: [pinia],
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    });
  }

  it('submits accessible fields and displays the success confirmation', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { message: 'Mot de passe modifie.' } }),
    } as Response);

    const wrapper = mountView();

    expect(wrapper.get('label[for="currentPassword-input"]').text()).toContain('Ancien');
    expect(wrapper.get('#currentPassword-input').attributes('autocomplete')).toBe('current-password');
    expect(wrapper.get('#password-input').attributes('autocomplete')).toBe('new-password');
    expect(wrapper.get('#passwordConfirmation-input').attributes('aria-describedby')).toBeUndefined();

    await wrapper.get('#currentPassword-input').setValue('old-password');
    await wrapper.get('#password-input').setValue('new-password123');
    await wrapper.get('#passwordConfirmation-input').setValue('new-password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/password', {
      method: 'PUT',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        current_password: 'old-password',
        password: 'new-password123',
        password_confirmation: 'new-password123',
      }),
    });
    expect(wrapper.get('[role="status"]').text()).toContain('bien ete modifie');
  });

  it('renders API errors on the relevant field', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: {
          message: 'Les donnees fournies sont invalides.',
          details: {
            fields: {
              current_password: ['Le mot de passe est incorrect.'],
            },
          },
        },
      }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#currentPassword-input').setValue('wrong-password');
    await wrapper.get('#password-input').setValue('new-password123');
    await wrapper.get('#passwordConfirmation-input').setValue('new-password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('#currentPassword-error').text()).toContain('incorrect');
    expect(wrapper.get('[role="alert"]').text()).toContain('invalides');
  });

  it('validates the form before calling the API', async () => {
    const wrapper = mountView();

    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Ce champ est requis.');
    expect(wrapper.text()).toContain('La confirmation du mot de passe est requise.');
  });
});
