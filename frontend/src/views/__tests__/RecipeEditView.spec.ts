import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeEditView from '../RecipeEditView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'recipe-id' } }),
  useRouter: () => ({ push: pushMock }),
}));

describe('RecipeEditView', () => {
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

  function recipeResponse() {
    return {
      ok: true,
      json: async () => ({
        success: true,
        data: {
          id: 'recipe-id',
          title: 'Tarte aux pommes',
          description: 'Une tarte maison.',
          slug: 'tarte-aux-pommes',
          prep_time_minutes: 20,
          cook_time_minutes: 40,
          servings: 6,
          source: 'Livre de recettes',
          author: { id: 7, name: 'Jane Doe' },
          ingredients: [{ position: 1, name: 'Pommes', quantity: 4, unit: 'pièces', preparation: '', is_optional: false, group_name: '' }],
          steps: [{ position: 1, instruction: 'Cuire.', duration_minutes: 40 }],
          tags: [{ id: 1, name: 'Dessert', slug: 'dessert', color: null }],
        },
      }),
    } as Response;
  }

  it('preloads every editable field and nested component', async () => {
    fetchMock.mockResolvedValue(recipeResponse());
    const wrapper = mount(RecipeEditView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect((wrapper.get('#recipe-title-edit-input').element as HTMLInputElement).value).toBe('Tarte aux pommes');
    expect((wrapper.get('#recipe-description-edit-input').element as HTMLTextAreaElement).value).toBe('Une tarte maison.');
    expect((wrapper.get('#recipe-prep-time-edit-input').element as HTMLInputElement).value).toBe('20');
    expect((wrapper.get('#ingredient-name-0').element as HTMLInputElement).value).toBe('Pommes');
    expect((wrapper.get('#step-instruction-0').element as HTMLTextAreaElement).value).toBe('Cuire.');
    expect(wrapper.text()).toContain('Dessert');
  });

  it('saves the preloaded recipe and redirects to its detail', async () => {
    fetchMock
      .mockResolvedValueOnce(recipeResponse())
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: { id: 'recipe-id', title: 'Tarte modifiée', slug: 'tarte-modifiee' } }),
      } as Response);
    const wrapper = mount(RecipeEditView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#recipe-title-edit-input').setValue('Tarte modifiée');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const request = fetchMock.mock.calls[1]?.[1];
    expect(request?.method).toBe('PATCH');
    expect(request?.headers).toEqual({
      Accept: 'application/json',
      Authorization: 'Bearer jwt-token',
      'Content-Type': 'application/json',
    });
    const payload = JSON.parse(String(request?.body));
    expect(payload.title).toBe('Tarte modifiée');
    expect(payload.ingredients[0].name).toBe('Pommes');
    expect(payload.steps[0].instruction).toBe('Cuire.');
    expect(payload.tags).toEqual(['Dessert']);
    expect(pushMock).toHaveBeenCalledWith({ name: 'recipe-detail', params: { id: 'recipe-id' } });
  });

  it('shows field errors and offers reload on a conflict', async () => {
    fetchMock
      .mockResolvedValueOnce(recipeResponse())
      .mockResolvedValueOnce({
        ok: false,
        status: 409,
        json: async () => ({
          success: false,
          error: {
            message: 'Conflit de modification.',
            details: { fields: { title: ['Le titre a changé entre-temps.'] } },
          },
        }),
      } as Response)
      .mockResolvedValueOnce(recipeResponse());
    const wrapper = mount(RecipeEditView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.text()).toContain('Conflit de modification.');
    expect(wrapper.text()).toContain('Le titre a changé entre-temps.');
    expect(wrapper.find('.reload-button').exists()).toBe(true);
    await wrapper.get('.reload-button').trigger('click');
    await flushPromises();
    expect(fetchMock).toHaveBeenCalledTimes(3);
  });
});
