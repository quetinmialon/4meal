import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookView from '../CookbookView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'cookbook-id' } }),
}));

describe('CookbookView', () => {
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
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
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
          data: [{ id: 'recipe-id', name: 'Pates', description: 'Rapide', created_at: null }],
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
});
