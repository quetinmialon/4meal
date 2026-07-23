import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeDetailView from '../RecipeDetailView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'recipe-id' } }),
}));

describe('RecipeDetailView', () => {
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
});
