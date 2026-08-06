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
      user: {
        id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null,
      },
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

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const request = fetchMock.mock.calls[0]?.[1];
    expect(request?.method).toBe('POST');
    expect(request?.headers).toEqual({
      Accept: 'application/json',
      Authorization: 'Bearer jwt-token',
    });
    expect(request?.body).toBeInstanceOf(FormData);
    expect((request?.body as FormData).get('name')).toBe('Mes recettes');
    expect((request?.body as FormData).get('slug')).toBeNull();
    expect((request?.body as FormData).get('description')).toBeNull();
    expect((request?.body as FormData).get('image')).toBeNull();
    expect(pushMock).toHaveBeenCalledWith({ name: 'cookbook', params: { id: '6e3a1d7b-4f48-4b30-9d6e-8cbe91b1bc30' } });
  });

  it('submits the cookbook description and selected image as multipart data', async () => {
    vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(() => 'blob:cookbook'), revokeObjectURL: vi.fn() });
    vi.stubGlobal('Image', class {
      width = 800;
      height = 600;
      onload: (() => void) | null = null;
      set src(_value: string) { queueMicrotask(() => this.onload?.()); }
    });
    fetchMock.mockResolvedValue({
      ok: true,
      status: 201,
      json: async () => ({ success: true, data: { id: 'cookbook-id', name: 'Cuisine', description: 'Description', image_path: 'cookbooks/image.png', image_url: '/storage/cookbooks/image.png', owner: { id: 7, name: 'Jane Doe' }, created_at: null } }),
    } as Response);

    const wrapper = mount(CookbookCreateForm, { global: { plugins: [testPinia] } });
    const image = new File(['image'], 'cookbook.png', { type: 'image/png' });
    await wrapper.get('#cookbook-name-input').setValue('Cuisine');
    await wrapper.get('#cookbook-description-input').setValue('Description');
    const input = wrapper.get('#cookbook-image-input');
    Object.defineProperty(input.element, 'files', { configurable: true, value: [image] });
    await input.trigger('change');
    await flushPromises();
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const body = fetchMock.mock.calls[0]?.[1]?.body as FormData;
    expect(body.get('description')).toBe('Description');
    expect(body.get('image')).toBe(image);
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
