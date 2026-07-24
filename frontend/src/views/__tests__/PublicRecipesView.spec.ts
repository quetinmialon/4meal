import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import PublicRecipesView from '../PublicRecipesView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}));

describe('PublicRecipesView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  it('loads public recipes with pagination', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: [{ id: 'recipe-id', title: 'Soupe publique', description: null, prep_time_minutes: null, cook_time_minutes: null, servings: null, tags: [], is_favorite: false }],
        meta: { pagination: { current_page: 1, per_page: 15, total: 16, last_page: 2, from: 1, to: 15, has_more_pages: true } },
      }),
    } as Response);

    const wrapper = mount(PublicRecipesView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/recipes?page=1&scope=public', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Soupe publique');
    expect(wrapper.get('[aria-label="Pagination des recettes"]').text()).toContain('Page 1 / 2');
  });
});
