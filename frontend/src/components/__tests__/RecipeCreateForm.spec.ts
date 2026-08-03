import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import RecipeCreateForm from '../RecipeCreateForm.vue';
import RecipeImageField from '../RecipeImageField.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}));

describe('RecipeCreateForm', () => {
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

  function cookbooksResponse() {
    return {
      ok: true,
      json: async () => ({
        success: true,
        data: [{ id: 'cookbook-id', name: 'Famille', owner: { id: 7, name: 'Jane Doe' }, member_role: 'owner' }],
        meta: { pagination: { current_page: 1, per_page: 15, total: 1, last_page: 1 } },
      }),
    } as Response;
  }

  it('validates the title, ingredients and steps before calling the API', async () => {
    fetchMock.mockResolvedValue(cookbooksResponse());
    const wrapper = mount(RecipeCreateForm, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(wrapper.text()).toContain('Le titre est requis.');
    expect(wrapper.text()).toContain('Le nom est requis.');
    expect(wrapper.text()).toContain('L’instruction est requise.');
  });

  it('adds ingredients, tags and steps, reorders steps, and sends the recipe payload', async () => {
    fetchMock
      .mockResolvedValueOnce(cookbooksResponse())
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ success: true, data: { id: 'recipe-id', title: 'P�tes', slug: 'pates' } }),
      } as Response);

    const wrapper = mount(RecipeCreateForm, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#recipe-title-input').setValue('P�tes');
    await wrapper.get('#ingredient-name-0').setValue('Tomates');
    await wrapper.get('#step-instruction-0').setValue('Cuire.');
    await wrapper.get('#recipe-prep-time-input').setValue('10');
    await wrapper.get('#recipe-servings-input').setValue('2');
    await wrapper.get('#recipe-tags-input').setValue('Rapide');
    await wrapper.get('#recipe-tags-input').trigger('keydown.enter');
    await wrapper.get('.secondary-button').trigger('click');
    await wrapper.get('#ingredient-name-1').setValue('Basilic');

    const stepAddButton = wrapper.findAll('.secondary-button').at(1);
    await stepAddButton?.trigger('click');
    await wrapper.get('#step-instruction-1').setValue('Servir.');
    await wrapper.findAll('.step-actions button').at(3)?.trigger('click');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const request = fetchMock.mock.calls[1]?.[1];
    expect(request?.method).toBe('POST');
    const payload = JSON.parse(String(request?.body));
    expect(payload).toMatchObject({
      title: 'P�tes',
      prep_time_minutes: 10,
      servings: 2,
      cookbook_id: null,
      tags: ['Rapide'],
    });
    expect(payload.ingredients[0].name).toBe('Tomates');
    expect(payload.steps.map((step: { instruction: string }) => step.instruction)).toEqual(['Servir.', 'Cuire.']);
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('selects a cookbook destination', async () => {
    fetchMock
      .mockResolvedValueOnce(cookbooksResponse())
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ success: true, data: { id: 'recipe-id', title: 'P�tes', slug: 'pates' } }),
      } as Response);
    const wrapper = mount(RecipeCreateForm, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#recipe-title-input').setValue('P�tes');
    await wrapper.get('#ingredient-name-0').setValue('Tomates');
    await wrapper.get('#step-instruction-0').setValue('Cuire.');
    await wrapper.find('input[type="radio"][value="cookbook"]').setValue();
    await wrapper.get('#recipe-cookbook-select').setValue('cookbook-id');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const payload = JSON.parse(String(fetchMock.mock.calls[1]?.[1]?.body));
    expect(payload.cookbook_id).toBe('cookbook-id');
  });

  it('displays API field errors without redirecting', async () => {
    fetchMock
      .mockResolvedValueOnce(cookbooksResponse())
      .mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => ({
          success: false,
          error: {
            message: 'Les données fournies sont invalides.',
            details: { fields: { title: ['Le titre est déjà utilisé.'] } },
          },
        }),
      } as Response);
    const wrapper = mount(RecipeCreateForm, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#recipe-title-input').setValue('P�tes');
    await wrapper.get('#ingredient-name-0').setValue('Tomates');
    await wrapper.get('#step-instruction-0').setValue('Cuire.');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('invalides');
    expect(wrapper.text()).toContain('Le titre est déjà utilisé.');
    expect(pushMock).not.toHaveBeenCalled();
  });

  it('sends a selected image as multipart data', async () => {
    fetchMock
      .mockResolvedValueOnce(cookbooksResponse())
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ success: true, data: { id: 'recipe-id', title: 'P�tes', slug: 'pates' } }),
      } as Response);
    const wrapper = mount(RecipeCreateForm, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#recipe-title-input').setValue('P�tes');
    await wrapper.get('#ingredient-name-0').setValue('Tomates');
    await wrapper.get('#step-instruction-0').setValue('Cuire.');

    const image = new File(['image'], 'recipe.png', { type: 'image/png' });
    wrapper.getComponent(RecipeImageField).vm.$emit('update:modelValue', image);
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const request = fetchMock.mock.calls[1]?.[1];
    expect(request?.body).toBeInstanceOf(FormData);
    expect((request?.body as FormData).get('image')).toBe(image);
    expect((request?.body as FormData).get('ingredients[0][name]')).toBe('Tomates');
    expect(request?.headers).not.toHaveProperty('Content-Type');
  });
});
