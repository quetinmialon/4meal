import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeHistoryView from '../RecipeHistoryView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'recipe-id' } }),
}));

describe('RecipeHistoryView', () => {
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

  it('loads and summarizes the recipe history', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: [
          { id: 2, type: 'updated', author: { id: 7, name: 'Jane Doe' }, old_values: { title: 'Ancien titre', ingredients_count: 1 }, new_values: { title: 'Nouveau titre', ingredients_count: 2 }, created_at: '2026-08-03T10:00:00Z' },
          { id: 1, type: 'created', author: { id: 7, name: 'Jane Doe' }, old_values: null, new_values: { title: 'Ancien titre' }, created_at: '2026-08-02T09:00:00Z' },
        ],
        meta: { pagination: { per_page: 20, next_cursor: null, previous_cursor: null } },
      }),
    } as Response);

    const wrapper = mount(RecipeHistoryView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-id/history?per_page=20', {
      credentials: 'include',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Modification du titre, des ingrédients');
    expect(wrapper.text()).toContain('Création');
    expect(wrapper.text()).toContain('Jane Doe');
    expect(wrapper.find('time[datetime="2026-08-03T10:00:00Z"]').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Restaurer');
  });

  it('shows loading, empty and error states', async () => {
    let resolveResponse!: (response: Response) => void;
    fetchMock.mockReturnValue(new Promise((resolve) => { resolveResponse = resolve; }));
    const wrapper = mount(RecipeHistoryView, { global: { plugins: [testPinia] } });
    expect(wrapper.get('[role="status"]').text()).toContain('Chargement');

    resolveResponse({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: { per_page: 20, next_cursor: null, previous_cursor: null } } }) } as Response);
    await flushPromises();
    expect(wrapper.text()).toContain('Aucun changement enregistré');

    wrapper.unmount();
    fetchMock.mockResolvedValue({ ok: false, json: async () => ({ success: false, error: { message: 'Historique indisponible.' } }) } as Response);
    const errorWrapper = mount(RecipeHistoryView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(errorWrapper.text()).toContain('Historique indisponible.');
  });
});
