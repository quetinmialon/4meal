import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeDetailView from '../RecipeDetailView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'recipe-id' } }),
  useRouter: () => ({ push: pushMock }),
}));

describe('RecipeDetailView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  function commentsUnavailableResponse(): Response {
    return {
      ok: false,
      status: 403,
      json: async () => ({ success: false, error: { message: 'Commentaires indisponibles.' } }),
    } as Response;
  }

  it('displays recipe metadata and all details', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: {
          id: 'recipe-id', title: 'Tarte aux pommes', description: 'Une tarte simple.',
          prep_time_minutes: 20, cook_time_minutes: 40, servings: 6,
          source: 'https://example.test/tarte', author: { id: 7, name: 'Jane Doe' },
          tags: [{ id: 1, name: 'Dessert', slug: 'dessert', color: null }],
          ingredients: [{ position: 1, name: 'Pommes', quantity: 4, unit: 'pièces', preparation: null, is_optional: false }],
          steps: [{ position: 1, instruction: 'Cuire.', duration_minutes: 40 }],
        },
      }),
    } as Response);

    const wrapper = mount(RecipeDetailView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.text()).toContain('Tarte aux pommes');
    expect(wrapper.text()).toContain('Préparation : 20 min');
    expect(wrapper.text()).toContain('Portions : 6');
    expect(wrapper.text()).toContain('Pommes');
    expect(wrapper.text()).toContain('Cuire.');
    expect(wrapper.text()).toContain('Dessert');
    expect(wrapper.text()).toContain('Jane Doe');
    expect(wrapper.find('a[href="https://example.test/tarte"]').exists()).toBe(true);
  });

  it('shows detail loading and error states', async () => {
    fetchMock.mockResolvedValue({ ok: false, status: 403, json: async () => ({ success: false, error: { message: 'Accès refusé' } }) } as Response);
    const wrapper = mount(RecipeDetailView, { global: { plugins: [testPinia] } });
    expect(wrapper.get('[role="status"]').text()).toContain('Chargement');
    await flushPromises();
    expect(wrapper.text()).toContain('Accès refusé');
  });

  it('adds the recipe to a selected cookbook from the detail view', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: { id: 'recipe-id', title: 'Tarte', is_favorite: false, author: null, ingredients: [], steps: [], tags: [] } }),
      } as Response)
      .mockResolvedValueOnce(commentsUnavailableResponse())
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [{ id: 'cookbook-id', name: 'Mes desserts' }], meta: { pagination: { current_page: 1, per_page: 15, total: 1, last_page: 1, from: 1, to: 1, has_more_pages: false } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(RecipeDetailView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.cookbook-button').trigger('click');
    await flushPromises();
    await wrapper.get('#recipe-cookbook').setValue('cookbook-id');
    await wrapper.get('.cookbook-picker').trigger('submit');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id/recipes/recipe-id', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.find('.cookbook-picker').exists()).toBe(false);
  });
  it('requires confirmation, deletes the recipe and redirects to the list', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          data: { id: 'recipe-id', title: 'A supprimer', prep_time_minutes: null, cook_time_minutes: null, servings: null, source: null, author: null, ingredients: [], steps: [], tags: [] },
        }),
      } as Response)
      .mockResolvedValueOnce(commentsUnavailableResponse())
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(RecipeDetailView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    expect(wrapper.text()).toContain('Supprimer cette recette ?');
    expect(fetchMock).toHaveBeenCalledTimes(2);

    await wrapper.get('.delete-confirmation .delete-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(3, '/api/recipes/recipe-id', {
      method: 'DELETE',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'recipes' });
  });

  it('shows the API deletion error and keeps the recipe visible', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          data: { id: 'recipe-id', title: 'Protegee', prep_time_minutes: null, cook_time_minutes: null, servings: null, source: null, author: null, ingredients: [], steps: [], tags: [] },
        }),
      } as Response)
      .mockResolvedValueOnce(commentsUnavailableResponse())
      .mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({ success: false, error: { message: 'Vous ne pouvez pas supprimer cette recette.' } }),
      } as Response);

    const wrapper = mount(RecipeDetailView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    await wrapper.get('.delete-confirmation .delete-button').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Vous ne pouvez pas supprimer cette recette.');
    expect(wrapper.text()).toContain('Protegee');
    expect(pushMock).not.toHaveBeenCalled();
  });
});
