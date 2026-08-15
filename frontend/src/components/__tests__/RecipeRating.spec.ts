import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeRating from '../RecipeRating.vue';

describe('RecipeRating', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
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

  it('exposes a keyboard-accessible radio group and saves a new rating', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { rating: 4 } }),
    } as Response);

    const wrapper = mount(RecipeRating, {
      props: { recipeId: 'recipe-id', personalRating: 2 },
      global: { plugins: [testPinia] },
    });

    expect(wrapper.get('fieldset legend').text()).toBe('Votre note');
    expect(wrapper.findAll('input[type="radio"]')).toHaveLength(5);
    expect(wrapper.get('input[value="2"]').attributes('aria-label')).toBe('2 sur 5');
    expect((wrapper.get('input[value="2"]').element as HTMLInputElement).checked).toBe(true);

    await wrapper.get('input[value="4"]').setValue();
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-id/rating', {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token', 'Content-Type': 'application/json' },
      body: JSON.stringify({ rating: 4 }),
    });
    expect((wrapper.get('input[value="4"]').element as HTMLInputElement).checked).toBe(true);
    expect(wrapper.text()).toContain('Votre note a été enregistrée.');
  });

  it('rolls back the optimistic update and announces API errors', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({ success: false, error: { message: 'Note invalide' } }),
    } as Response);

    const wrapper = mount(RecipeRating, {
      props: { recipeId: 'recipe-id', personalRating: 3 },
      global: { plugins: [testPinia] },
    });

    await wrapper.get('input[value="5"]').setValue();
    expect((wrapper.get('input[value="5"]').element as HTMLInputElement).checked).toBe(true);
    await flushPromises();

    expect((wrapper.get('input[value="3"]').element as HTMLInputElement).checked).toBe(true);
    expect(wrapper.get('[role="alert"]').text()).toBe('Note invalide');
  });

  it('removes the current rating', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(RecipeRating, {
      props: { recipeId: 'recipe-id', personalRating: 5 },
      global: { plugins: [testPinia] },
    });

    await wrapper.get('.clear-rating').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-id/rating', {
      method: 'DELETE',
      credentials: 'include',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Aucune note');
    expect(wrapper.find('.clear-rating').exists()).toBe(false);
  });
});
