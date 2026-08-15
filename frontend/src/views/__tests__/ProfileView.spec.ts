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
    Object.defineProperty(URL, 'createObjectURL', { value: vi.fn(() => 'blob:avatar'), configurable: true });
    Object.defineProperty(URL, 'revokeObjectURL', { value: vi.fn(), configurable: true });
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
        avatar_url: 'https://example.test/storage/avatars/jane.webp',
        last_login_at: null,
        created_at: null,
        diet: 'vegetarian',
        allergies: ['arachides'],
        default_servings: 3,
        theme: 'light',
      },
    });

    return mount(ProfileView, { global: { plugins: [pinia] } });
  }

  function profileRequest(): [RequestInfo | URL, RequestInit | undefined] {
    const call = fetchMock.mock.calls.find(([input]) => String(input) === '/api/auth/me');
    if (call === undefined) throw new Error('Profile request was not sent.');
    return [call[0], call[1]];
  }

  function profileRequestCount(): number {
    return fetchMock.mock.calls.filter(([input]) => String(input) === '/api/auth/me').length;
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
    expect(wrapper.get('#avatar-input').attributes('type')).toBe('file');
    expect(wrapper.get('img.avatar-preview').attributes('src')).toBe('https://example.test/storage/avatars/jane.webp');
    expect((wrapper.get('#currentPassword-input').element as HTMLInputElement).value).toBe('');

    await wrapper.get('#name-input').setValue('Jane Smith');
    const avatarInput = wrapper.get('#avatar-input').element as HTMLInputElement;
    Object.defineProperty(avatarInput, 'files', {
      value: [new File(['avatar'], 'jane-smith.webp', { type: 'image/webp' })],
      configurable: true,
    });
    await wrapper.get('#avatar-input').trigger('change');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/auth/me', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
      },
      body: expect.any(FormData),
    });
    const body = profileRequest()[1]?.body as FormData;
    expect(body.get('_method')).toBe('PATCH');
    expect(body.get('name')).toBe('Jane Smith');
    expect(body.get('avatar')).toBeInstanceOf(File);
    expect(wrapper.get('[role="status"]').text()).toContain('bien ete modifie');
  });

  it('loads and saves notification preferences using only supported channels', async () => {
    fetchMock.mockImplementation(async (input) => {
      if (String(input) === '/api/notifications/preferences') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [
            { type: 'recipe_comment', channel: 'mail' },
            { type: 'recipe_comment_reply', channel: 'none' },
            { type: 'cookbook_message', channel: 'both' },
          ] }),
        } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ success: true, data: {} }) } as Response;
    });

    const wrapper = mountView();
    await flushPromises();

    expect((wrapper.get('#recipe_comment-notification-input').element as HTMLSelectElement).value).toBe('mail');
    expect(wrapper.find('#recipe_comment-notification-input option[value="push"]').exists()).toBe(false);
    await wrapper.get('#cookbook_message-notification-input').setValue('web');
    const notificationButton = wrapper.findAll('button').find((button) => button.text().includes('notifications'));
    if (notificationButton === undefined) throw new Error('Notification save button was not rendered.');
    await notificationButton.trigger('click');
    await flushPromises();

    const putRequest = fetchMock.mock.calls.find(([input, init]) => String(input) === '/api/notifications/preferences' && init?.method === 'PUT');
    expect(putRequest).toBeDefined();
    expect(JSON.parse(String(putRequest?.[1]?.body))).toEqual({ preferences: [
      { type: 'recipe_comment', channel: 'mail' },
      { type: 'recipe_comment_reply', channel: 'none' },
      { type: 'cookbook_message', channel: 'web' },
    ] });
  });

  it('requires the current password and sends it when the email changes', async () => {
    const wrapper = mountView();

    await wrapper.get('#email-input').setValue('new@example.com');
    await wrapper.get('form').trigger('submit.prevent');

    expect(profileRequestCount()).toBe(0);
    expect(wrapper.get('#currentPassword-error').text()).toContain('requis');

    await wrapper.get('#currentPassword-input').setValue('password123');
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { id: 7, name: 'Jane Doe', email: 'new@example.com', avatar_path: null, last_login_at: null, created_at: null } }),
    } as Response);
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const body = profileRequest()[1]?.body as FormData;
    expect(body.get('current_password')).toBe('password123');
  });

  it('edits culinary preferences, displays help and sends normalized values', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null } }),
    } as Response);

    const wrapper = mountView();

    expect(wrapper.get('#food-preferences-title').text()).toContain('Préférences culinaires');
    expect(wrapper.get('#diet-help').text()).toContain('valeur dans la liste');
    expect((wrapper.get('#defaultServings-input').element as HTMLInputElement).value).toBe('3');
    expect(wrapper.get('.tag').text()).toContain('arachides');

    await wrapper.get('#diet-input').setValue('vegan');
    await wrapper.get('#allergy-input').setValue('lait');
    await wrapper.get('#allergy-input').trigger('keydown.enter');
    await wrapper.get('#defaultServings-input').setValue('4');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const body = profileRequest()[1]?.body as FormData;
    expect(body.get('diet')).toBe('vegan');
    expect(body.getAll('allergies[]')).toEqual(['arachides', 'lait']);
    expect(body.get('default_servings')).toBe('4');
  });

  it('changes the theme immediately and synchronizes explicit themes', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, theme: 'dark', last_login_at: null, created_at: null } }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#theme-input').setValue('dark');
    expect(document.documentElement.dataset.theme).toBe('dark');

    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    const body = profileRequest()[1]?.body as FormData;
    expect(body.get('theme')).toBe('dark');
    expect(window.localStorage.getItem('4meal.theme.preference')).toBe('dark');
  });

  it('validates portions and allergy labels before submitting', async () => {
    const wrapper = mountView();

    await wrapper.get('#defaultServings-input').setValue('0');
    await wrapper.get('#allergy-input').setValue('a'.repeat(101));
    await wrapper.get('#allergy-input').trigger('keydown.enter');
    await wrapper.get('form').trigger('submit.prevent');

    expect(profileRequestCount()).toBe(0);
    expect(wrapper.get('#defaultServings-error').text()).toContain('entre 1 et 50');
    expect(wrapper.get('#allergy-error').text()).toContain('100 caractères');
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

  it('displays API errors for culinary preferences', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: { message: 'Les préférences sont invalides.', details: { fields: { diet: ['Choisissez un régime valide.'], default_servings: ['Le nombre de portions est invalide.'] } } },
      }),
    } as Response);

    const wrapper = mountView();
    await wrapper.get('#diet-input').setValue('vegan');
    await wrapper.get('#defaultServings-input').setValue('4');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('#diet-error').text()).toContain('régime valide');
    expect(wrapper.get('#defaultServings-error').text()).toContain('portions est invalide');
  });

  it('validates required fields before calling the API', async () => {
    const wrapper = mountView();
    await wrapper.get('#name-input').setValue('');
    await wrapper.get('#email-input').setValue('invalid');
    await wrapper.get('form').trigger('submit.prevent');

    expect(profileRequestCount()).toBe(0);
    expect(wrapper.text()).toContain('Le nom est requis.');
    expect(wrapper.text()).toContain('adresse e-mail valide');
  });
});
