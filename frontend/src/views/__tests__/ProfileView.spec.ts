import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ProfileView from '../ProfileView.vue';

describe('ProfileView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    window.localStorage.clear();
    setActivePinia(createPinia());
  });

  function mountView() {
    const pinia = createPinia();
    const authStore = useAuthStore(pinia);

    authStore.applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: {
        id: 7,
        name: 'Jane Doe',
        email: 'jane@example.com',
        avatar_path: 'avatars/jane.webp',
        last_login_at: null,
        created_at: null,
      },
    });

    return mount(ProfileView, { global: { plugins: [pinia] } });
  }

  it('prefills the profile form and confirms a successful update', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: {
          id: 7,
          name: 'Jane Smith',
          email: 'jane@example.com',
          avatar_path: 'avatars/jane-smith.webp',
          last_login_at: null,
          created_at: null,
        },
      }),
    } as Response);

    const wrapper = mountView();

    expect((wrapper.get('#name-input').element as HTMLInputElement).value).toBe('Jane Doe');
    expect((wrapper.get('#email-input').element as HTMLInputElement).value).toBe('jane@example.com');
    expect((wrapper.get('#avatarPath-input').element as HTMLInputElement).value).toBe('avatars/jane.webp');
    expect((wrapper.get('#currentPassword-input').element as HTMLInputElement).value).toBe('');

    await wrapper.get('#name-input').setValue('Jane Smith');
    await wrapper.get('#avatarPath-input').setValue('avatars/jane-smith.webp');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/me', {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: 'Jane Smith',
        avatar_path: 'avatars/jane-smith.webp',
      }),
    });
    expect(wrapper.get('[role="status"]').text()).toContain('bien ete modifie');
  });

  it('requires the current password and sends it when the email changes', async () => {
    const wrapper = mountView();

    await wrapper.get('#email-input').setValue('new@example.com');
    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('#currentPassword-error').text()).toContain('requis');

    await wrapper.get('#currentPassword-input').setValue('password123');
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { id: 7, name: 'Jane Doe', email: 'new@example.com', avatar_path: null, last_login_at: null, created_at: null } }),
    } as Response);
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({ body: expect.stringContaining('current_password') });
  });

  it('displays API field errors and a global error', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: {
          message: 'Les donnees fournies sont invalides.',
          details: { fields: { email: ['Cette adresse est deja utilisee.'] } },
        },
      }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#email-input').setValue('new@example.com');
    await wrapper.get('#currentPassword-input').setValue('password123');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('#email-error').text()).toContain('deja utilisee');
    expect(wrapper.get('[role="alert"]').text()).toContain('invalides');
  });

  it('validates required fields before calling the API', async () => {
    const wrapper = mountView();
    await wrapper.get('#name-input').setValue('');
    await wrapper.get('#email-input').setValue('invalid');
    await wrapper.get('form').trigger('submit.prevent');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Le nom est requis.');
    expect(wrapper.text()).toContain('adresse e-mail valide');
  });
});
