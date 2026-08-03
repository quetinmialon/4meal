import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookView from '../CookbookView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => ({ params: { id: 'cookbook-id' } }),
  useRouter: () => ({ push: pushMock }),
}));

describe('CookbookView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  function membersPage(data: unknown[] = [], total = data.length) {
    return {
      success: true,
      data,
      meta: { pagination: { current_page: 1, per_page: 15, total, last_page: 1, from: total ? 1 : null, to: total || null, has_more_pages: false } },
    };
  }

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
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
          data: [{
            id: 'recipe-id', title: 'Pates', slug: 'pates', description: 'Rapide',
            prep_time_minutes: null, cook_time_minutes: null, rest_time_minutes: null,
            servings: null, image_path: null, visibility: null, difficulty: null, notes: null, created_at: null,
          }],
          meta: { pagination: { current_page: 1, per_page: 1, total: 2, last_page: 2, from: 1, to: 1, has_more_pages: true } },
        }),
      } as Response);
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'viewer', joined_at: null, status: 'active' },
        { user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'editor', joined_at: null, status: 'active' },
      ], 2),
    } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Votre rôle : viewer');
    expect(wrapper.text()).toContain('Pates');
    expect(wrapper.text()).toContain('Alex Martin');
    expect(wrapper.text()).toContain('alex@example.com');
    expect(wrapper.text()).toContain('Aucune action');
    expect(wrapper.text()).toContain('Propriétaire protégé');
    expect(wrapper.findAll('button').map((button) => button.text())).toContain('Suivant');
    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/cookbooks/cookbook-id/recipes?page=1', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
  });

  it('allows an editor to rename the cookbook and updates the displayed name', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Ancien nom',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'editor', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage() } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Nouveau nom',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'editor', created_at: null,
        } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.edit-button').trigger('click');
    await wrapper.get('#cookbook-name-edit-input').setValue('Nouveau nom');
    await wrapper.get('.edit-name-form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledTimes(4);
    const updateRequest = fetchMock.mock.calls[3]?.[1];
    expect(updateRequest?.method).toBe('POST');
    expect(updateRequest?.headers).toEqual({
      Accept: 'application/json',
      Authorization: 'Bearer jwt-token',
    });
    expect(updateRequest?.body).toBeInstanceOf(FormData);
    expect((updateRequest?.body as FormData).get('_method')).toBe('PATCH');
    expect((updateRequest?.body as FormData).get('name')).toBe('Nouveau nom');
    expect(wrapper.text()).toContain('Nouveau nom');
    expect(wrapper.find('.edit-name-form').exists()).toBe(false);
  });

  it('hides the rename action for a viewer', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Lecture seule',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'viewer', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage() } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(wrapper.find('.edit-button').exists()).toBe(false);
    expect(wrapper.find('.delete-button').exists()).toBe(false);
    expect(wrapper.text()).toContain('Aucun membre dans ce cookbook.');
  });

  it('shows member pagination and loads the next page', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Membres',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({
        success: true,
        data: [{ user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'viewer', joined_at: null, status: 'active' }],
        meta: { pagination: { current_page: 1, per_page: 1, total: 2, last_page: 2, from: 1, to: 1, has_more_pages: true } },
      }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({
        success: true,
        data: [{ user: { id: 9, name: 'Sam Dupont', email: 'sam@example.com' }, role: 'editor', joined_at: null, status: 'active' }],
        meta: { pagination: { current_page: 2, per_page: 1, total: 2, last_page: 2, from: 2, to: 2, has_more_pages: false } },
      }) } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    const pagination = wrapper.get('[aria-label="Pagination des membres"]');
    expect(pagination.text()).toContain('Page 1 / 2');
    await pagination.findAll('button')[1]?.trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Sam Dupont');
    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id/members?page=2', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
  });

  it('shows the members loading state while the list is pending', async () => {
    let resolveMembers!: (response: Response) => void;
    const pendingMembers = new Promise<Response>((resolve) => { resolveMembers = resolve; });
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Membres',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'viewer', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockReturnValueOnce(pendingMembers);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.text()).toContain('Chargement des membres...');

    resolveMembers({ ok: true, json: async () => membersPage() } as Response);
    await flushPromises();
    expect(wrapper.text()).toContain('Aucun membre dans ce cookbook.');
  });

  it('lets an owner request and confirm a member role change, then refreshes the list', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Permissions',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'owner', joined_at: null, status: 'active' },
        { user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'reader', joined_at: null, status: 'active' },
      ], 2) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: {
        user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'editor', joined_at: null, status: 'active',
      } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'owner', joined_at: null, status: 'active' },
        { user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'editor', joined_at: null, status: 'active' },
      ], 2) } as Response);
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ success: true, data: {
        id: 'cookbook-id', name: 'Permissions',
        owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
        member_role: 'editor', created_at: null,
      } }),
    } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();

    await wrapper.get('#member-role-8').setValue('editor');
    await wrapper.get('#member-role-8').trigger('change');
    await wrapper.get('.member-role-form').trigger('submit.prevent');

    expect(wrapper.get('[role="dialog"]').text()).toContain('Alex Martin');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Éditeur');
    expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true');
    expect(wrapper.get('[role="dialog"]').attributes('aria-labelledby')).toBe('role-dialog-title');

    await wrapper.get('[role="dialog"] .edit-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id/members/8/role', {
      method: 'PATCH',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ role: 'editor' }),
    });
    expect(fetchMock).toHaveBeenNthCalledWith(5, '/api/cookbooks/cookbook-id/members?page=1', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.findAll('.member-item')[1]?.text()).toContain('Éditeur');
    expect(wrapper.text()).toContain('editor');
    expect(wrapper.findAll('[role="dialog"]').length).toBe(0);
  });

  it('keeps the confirmation dialog open and announces an authorization error', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Permissions',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'owner', joined_at: null, status: 'active' },
        { user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'reader', joined_at: null, status: 'active' },
      ], 2) } as Response)
      .mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({ success: false, error: { message: 'Vous n’êtes pas autorisé à modifier les rôles.' } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#member-role-8').setValue('commenter');
    await wrapper.get('.member-role-form').trigger('submit.prevent');
    await wrapper.get('[role="dialog"] .edit-button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="dialog"] [role="alert"]').text()).toContain('pas autorisé');
    expect(wrapper.findAll('[role="dialog"]').length).toBe(1);
    await wrapper.get('[role="dialog"] .cancel-button').trigger('click');
    expect(wrapper.findAll('[role="dialog"]').length).toBe(0);
  });

  it('confirms leaving a cookbook and redirects the member to the dashboard', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'À quitter',
          owner: { id: 9, name: 'Owner', email: 'owner@example.com', created_at: null },
          member_role: 'reader', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 9, name: 'Owner', email: 'owner@example.com' }, role: 'owner', joined_at: null, status: 'active' },
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'reader', joined_at: null, status: 'active' },
      ], 2) } as Response)
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.get('.member-leave-button').text()).toBe('Quitter');

    await wrapper.get('.member-leave-button').trigger('click');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Confirmer votre départ');
    expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe('true');
    await wrapper.get('[role="dialog"] .edit-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id/members/me', {
      method: 'DELETE',
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('confirms removing a member, refreshes the list and announces authorization errors', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'Membres',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {
        current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'owner', joined_at: null, status: 'active' },
        { user: { id: 8, name: 'Alex Martin', email: 'alex@example.com' }, role: 'reader', joined_at: null, status: 'active' },
      ], 2) } as Response)
      .mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({ success: false, error: { message: 'Action non autorisée.' } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage([
        { user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' }, role: 'owner', joined_at: null, status: 'active' },
      ], 1) } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.member-remove-button').trigger('click');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Alex Martin');
    await wrapper.get('[role="dialog"] .edit-button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="dialog"] [role="alert"]').text()).toContain('Action non autorisée');
    await wrapper.get('[role="dialog"] .cancel-button').trigger('click');
    await wrapper.get('.member-remove-button').trigger('click');
    await wrapper.get('[role="dialog"] .edit-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id/members/8', {
      method: 'DELETE',
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(fetchMock).toHaveBeenNthCalledWith(5, '/api/cookbooks/cookbook-id/members/8', {
      method: 'DELETE',
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(fetchMock).toHaveBeenNthCalledWith(6, '/api/cookbooks/cookbook-id/members?page=1', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).not.toContain('Alex Martin');
  });

  it('requires the exact cookbook name and redirects after successful deletion', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'A supprimer',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage() } as Response)
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    await wrapper.get('.delete-form').trigger('submit.prevent');

    expect(wrapper.get('#delete-confirmation-error').text()).toContain('exactement');
    expect(fetchMock).toHaveBeenCalledTimes(3);

    await wrapper.get('#delete-confirmation-input').setValue('A supprimer');
    await wrapper.get('.delete-form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(4, '/api/cookbooks/cookbook-id', {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        Authorization: 'Bearer jwt-token',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ confirmation: 'A supprimer' }),
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard' });
  });

  it('displays a backend error and exits the loading state', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: {
          id: 'cookbook-id', name: 'A supprimer',
          owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
          member_role: 'owner', created_at: null,
        } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [], meta: { pagination: {
          current_page: 1, per_page: 15, total: 0, last_page: 1, from: null, to: null, has_more_pages: false,
        } } }),
      } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => membersPage() } as Response)
      .mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({ success: false, error: { message: 'Action non autorisee.' } }),
      } as Response);

    const wrapper = mount(CookbookView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.delete-button').trigger('click');
    await wrapper.get('#delete-confirmation-input').setValue('A supprimer');
    await wrapper.get('.delete-form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('.delete-form').text()).toContain('Action non autorisee.');
    expect(wrapper.get('.delete-form button[type="submit"]').attributes('disabled')).toBeUndefined();
    expect(pushMock).not.toHaveBeenCalled();
  });
});
