import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import DashboardView from '../DashboardView.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRouter: () => ({ push: pushMock }),
}));

describe('DashboardView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

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

  function paginated(data: unknown[], total = data.length) {
    return {
      success: true,
      data,
      meta: { pagination: { current_page: 1, per_page: 15, total, last_page: 1, from: total ? 1 : null, to: total || null, has_more_pages: false } },
    };
  }

  it('displays the empty state when the user has no cookbooks', async () => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => paginated([]) } as Response);

    const wrapper = mount(DashboardView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks?page=1', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Vous n’avez encore aucun cookbook.');
  });

  it('lists cookbooks and displays each member role', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => paginated([{
        id: 'cookbook-id',
        name: 'Mes recettes',
        owner: { id: 7, name: 'Jane Doe', email: 'jane@example.com', created_at: null },
        member_role: 'editor',
        created_at: null,
      }]),
    } as Response);

    const wrapper = mount(DashboardView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Mes recettes');
    expect(wrapper.get('.role-badge').text()).toBe('editor');
  });

  it('lists pending invitations and accepts one', async () => {
    fetchMock.mockImplementation(async (input) => {
      if (String(input) === '/api/invitations') {
        return { ok: true, json: async () => ({ success: true, data: [{ id: 12, email: 'jane@example.com', role: 'viewer', expires_at: '2099-01-01T00:00:00Z', accepted_at: null, cookbook: { id: 'book', name: 'Chez nous' } }] }) } as Response;
      }
      if (String(input) === '/api/invitations/12/accept') {
        return { ok: true, json: async () => ({ success: true, data: { invitation: { id: 12, accepted_at: '2026-07-23T00:00:00Z' }, cookbook: { id: 'book', name: 'Chez nous', role: 'viewer' } } }) } as Response;
      }
      return { ok: true, json: async () => paginated([]) } as Response;
    });

    const wrapper = mount(DashboardView, { global: { plugins: [testPinia] } });
    await flushPromises();
    expect(wrapper.text()).toContain('Chez nous');
    await wrapper.get('.accept-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/invitations/12/accept', expect.objectContaining({ method: 'POST' }));
    expect(wrapper.text()).toContain('Aucune invitation en attente.');
  });

  it('removes an invitation after refusing it', async () => {
    fetchMock.mockImplementation(async (input) => {
      if (String(input) === '/api/invitations') {
        return { ok: true, json: async () => ({ success: true, data: [{ id: 13, email: 'jane@example.com', role: 'editor', expires_at: '2099-01-01T00:00:00Z', accepted_at: null, cookbook: { id: 'book', name: 'À refuser' } }] }) } as Response;
      }
      if (String(input) === '/api/invitations/13/decline') return { ok: true, json: async () => ({ success: true, data: {} }) } as Response;
      return { ok: true, json: async () => paginated([]) } as Response;
    });

    const wrapper = mount(DashboardView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.decline-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/invitations/13/decline', expect.objectContaining({ method: 'POST' }));
    expect(wrapper.text()).toContain('Aucune invitation en attente.');
  });
});
