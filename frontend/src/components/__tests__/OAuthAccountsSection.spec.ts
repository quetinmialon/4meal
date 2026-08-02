import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import OAuthAccountsSection from '../OAuthAccountsSection.vue';

describe('OAuthAccountsSection', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.history.replaceState({}, '', '/profil');
    window.confirm = vi.fn(() => true);
    setActivePinia(createPinia());
  });

  function mountSection() {
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

    return mount(OAuthAccountsSection, { global: { plugins: [pinia] } });
  }

  it('affiche l’avertissement et charge les fournisseurs liés à l’ouverture', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: [{ id: 1, provider: 'google', email: 'jane@gmail.com', token_expires_at: null, created_at: null }],
      }),
    } as Response);

    const wrapper = mountSection();
    expect(wrapper.get('.oauth-warning').text()).toContain('au moins un moyen de connexion');

    await wrapper.get('summary').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/oauth-accounts', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Google');
    expect(wrapper.text()).toContain('jane@gmail.com');
    expect(wrapper.text()).toContain('Associer Microsoft');
  });

  it('affiche les erreurs de chargement et permet de réessayer', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: { message: 'Service indisponible.' } }),
    } as Response);

    const wrapper = mountSection();
    await wrapper.get('summary').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('Service indisponible');
    expect(wrapper.get('.inline-button').text()).toContain('Réessayer');
  });

  it('déclenche la liaison d’un fournisseur', async () => {
    const assign = vi.fn();
    Object.defineProperty(window, 'location', { value: { assign }, configurable: true });
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { authorization_url: 'https://login.microsoftonline.com/oauth' } }),
    } as Response);

    const wrapper = mountSection();
    await wrapper.get('summary').trigger('click');
    await flushPromises();

    await wrapper.get('button.secondary-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenLastCalledWith('/api/auth/oauth/google/link', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(assign).toHaveBeenCalledWith('https://login.microsoftonline.com/oauth');
  });

  it('supprime un fournisseur après confirmation et affiche le succès', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 1, provider: 'google', email: 'jane@gmail.com', token_expires_at: null, created_at: null }] }) } as Response)
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ success: true, data: {} }) } as Response);

    const wrapper = mountSection();
    await wrapper.get('summary').trigger('click');
    await flushPromises();
    await wrapper.get('.danger-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenLastCalledWith('/api/auth/oauth/google', {
      method: 'DELETE',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Google a été dissocié');
    expect(wrapper.text()).not.toContain('jane@gmail.com');
  });

  it('affiche l’erreur de suppression et respecte une annulation', async () => {
    window.confirm = vi.fn(() => false);
    fetchMock.mockResolvedValue({ ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 1, provider: 'google', email: 'jane@gmail.com', token_expires_at: null, created_at: null }] }) } as Response);

    const wrapper = mountSection();
    await wrapper.get('summary').trigger('click');
    await flushPromises();
    await wrapper.get('.danger-button').trigger('click');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(wrapper.text()).toContain('jane@gmail.com');
  });

  it('affiche clairement le refus de supprimer le dernier moyen de connexion', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 1, provider: 'google', email: 'jane@gmail.com', token_expires_at: null, created_at: null }] }) } as Response)
      .mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => ({
          success: false,
          error: {
            message: 'api.validation_error',
            details: { fields: { provider: ['Impossible de supprimer votre dernier moyen de connexion.'] } },
          },
        }),
      } as Response);

    const wrapper = mountSection();
    await wrapper.get('summary').trigger('click');
    await flushPromises();
    await wrapper.get('.danger-button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('Impossible de supprimer votre dernier moyen de connexion.');
  });
});
