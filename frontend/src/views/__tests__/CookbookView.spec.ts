import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookView from '../CookbookView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'cookbook-id' } }),
  useRouter: () => ({ push: pushMock }),
}));

describe('CookbookView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null,
      },
    });
  });

  it('displays the cookbook role and paginated recipes', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id',
          name: 'Mes recettes',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'viewer',
          created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          data: [{
            id: 'recipe-id', title: 'Pates', slug: 'pates', description: 'Rapide',
            prep_time_minutes: null, cook_time_minutes: null, rest_time_minutes: null,
            servings: null, image_path: null, visibility: null, difficulty: null, notes: null, created_at: null,
          }],
          meta: { pagination: { current_page: 1, per_page: 1, total: 2, last_page: 2, from: 1, to: 1, has_more_pages: true } },
        }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Votre rôle : viewer');
    expect(wrapper.text()).toContain('Pates');
    expect(wrapper.findAll('button').map((button) => button.text())).toContain('Suivant');
    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/cookbooks/cookbook-id/recipes?page=1', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
  });

  it('allows an editor to rename the cookbook and updates the displayed name', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Ancien nom',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'editor', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Nouveau nom',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'editor', created_at: null,
        } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.edit-button').trigger('click');
    await wrapper.get('#cookbook-name-edit-input').setValue('Nouveau nom');
    await wrapper.get('.edit-name-form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(3, '/api/cookbooks/cookbook-id', {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ name: 'Nouveau nom' }),
    });
    expect(wrapper.text()).toContain('Nouveau nom');
    expect(wrapper.find('.edit-name-form').exists()).toBe(false);
  });

  it('hides the rename action for a viewer', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Lecture seule',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'viewer', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(wrapper.find('.edit-button').exists()).toBe(false);
    expect(wrapper.find('.delete-button').exists()).toBe(false);
  });

  it('requires the exact cookbook name and redirects after successful deletion', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'A supprimer',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    await wrapper.get('.delete-form').trigger('submit.prevent');

    expect(wrapper.get('#delete-confirmation-error').text()).toContain('exactement');
    expect(fetchMock).toHaveBeenCalledTimes(2);

    await wrapper.get('#delete-confirmation-input').setValue('A supprimer');
    await wrapper.get('.delete-form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(3, '/api/cookbooks/cookbook-id', {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ confirmation: 'A supprimer' }),
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('displays a backend error and exits the loading state', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'A supprimer',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({ success: false, error: { message: 'Action non autorisee.' } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    await wrapper.get('#delete-confirmation-input').setValue('A supprimer');
    await wrapper.get('.delete-form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('.delete-form').text()).toContain('Action non autorisee.');
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
    expect(pushMock).not.toHaveBeenCalled();
  });
});
