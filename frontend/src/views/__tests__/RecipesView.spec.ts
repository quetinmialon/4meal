import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipesView from '../RecipesView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}));

describe('RecipesView', () => {
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

  function page(data: unknown[], total = data.length, lastPage = 1) {
    return {
      ok: true,
      json: async () => ({
        success: true,
        data,
        meta: { pagination: { current_page: 1, per_page: 20, total, last_page: lastPage, from: data.length ? 1 : null, to: data.length || null, has_more_pages: lastPage > 1 } },
      }),
    } as Response;
  }

  it('shows loading then recipes and pagination', async () => {
    fetchMock.mockResolvedValue(page([{ id: 'recipe-id', title: 'Soupe', description: 'Maison', prep_time_minutes: 10, cook_time_minutes: null, servings: 2, tags: [] }], 2, 2));
    const wrapper = mount(RecipesView, { global: { plugins: [testPinia] } });
    expect(wrapper.get('[role="status"]').text()).toContain('Chargement');
    await flushPromises();
    expect(wrapper.text()).toContain('Soupe');
    expect(wrapper.text()).toContain('Voir la fiche de Soupe');
    expect(wrapper.find('[aria-label="Pagination des recettes"]').exists()).toBe(true);
  });

  it('shows the empty state', async () => {
    fetchMock.mockResolvedValue(page([]));
    const wrapper = mount(RecipesView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.text()).toContain('Aucune recette pour le moment');
  });

  it('shows an error and retries', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({ success: false, error: { message: 'Service indisponible' } }) } as Response)
      .mockResolvedValueOnce(page([{ id: 'recipe-id', title: 'Soupe' }]));
    const wrapper = mount(RecipesView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.text()).toContain('Service indisponible');
    await wrapper.get('[role="alert"] button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Soupe');
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
