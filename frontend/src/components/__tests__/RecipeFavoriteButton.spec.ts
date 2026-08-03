import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeFavoriteButton from '../RecipeFavoriteButton.vue';

describe('RecipeFavoriteButton', () => {
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

  it('updates immediately and persists adding a favorite', async () => {
    let resolveRequest!: (response: Response) => void;
    fetchMock.mockReturnValueOnce(new Promise((resolve) => { resolveRequest = resolve; }));

    const wrapper = mount(RecipeFavoriteButton, {
      props: { recipeId: 'recipe-id', isFavorite: false },
      global: { plugins: [testPinia] },
    });

    await wrapper.get('button').trigger('click');
    expect(wrapper.get('button').attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('button').text()).toContain('Favori');
    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-id/favorite', {
      method: 'POST',
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });

    resolveRequest({ ok: true, status: 204, json: async () => null } as Response);
    await flushPromises();
    expect(wrapper.get('button').attributes('aria-pressed')).toBe('true');
  });

  it('rolls back immediately after a failed removal', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: { message: 'Service indisponible' } }),
    } as Response);

    const wrapper = mount(RecipeFavoriteButton, {
      props: { recipeId: 'recipe-id', isFavorite: true },
      global: { plugins: [testPinia] },
    });

    await wrapper.get('button').trigger('click');
    expect(wrapper.get('button').attributes('aria-pressed')).toBe('false');
    await flushPromises();

    expect(wrapper.get('button').attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('[role="alert"]').text()).toContain('Service indisponible');
    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-id/favorite', {
      method: 'DELETE',
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
  });
});
