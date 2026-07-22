import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookCreateForm from '../CookbookCreateForm.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}));

describe('CookbookCreateForm', () => {
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
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
    });
  });

  it('validates the name before calling the API', async () => {
    const wrapper = mount(CookbookCreateForm, { global: { plugins: [testPinia] } });

    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('#cookbook-name-error').text()).toContain('requis');
  });

  it('creates a cookbook and redirects to it', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 201,
      json: async () => ({
        success: true,
        data: {
          id: '6e3a1d7b-4f48-4b30-9d6e-8cbe91b1bc30',
          name: 'Mes recettes',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          created_at: null,
        },
      }),
    } as Response);

    const wrapper = mount(CookbookCreateForm, { global: { plugins: [testPinia] } });
    await wrapper.get('#cookbook-name-input').setValue('  Mes recettes  ');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ name: 'Mes recettes' }),
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'cookbook', params: { id: '6e3a1d7b-4f48-4b30-9d6e-8cbe91b1bc30' } });
  });

  it('displays API validation errors without redirecting', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: {
          message: 'Les donnees fournies sont invalides.',
          details: { fields: { name: ['Le nom est obligatoire.'] } },
        },
      }),
    } as Response);

    const wrapper = mount(CookbookCreateForm, { global: { plugins: [testPinia] } });
    await wrapper.get('#cookbook-name-input').setValue('Cuisine');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('invalides');
    expect(wrapper.get('#cookbook-name-error').text()).toContain('obligatoire');
    expect(pushMock).not.toHaveBeenCalled();
  });
});
