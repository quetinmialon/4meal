import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookInvitationView from '../CookbookInvitationView.vue';

const pushMock = vi.fn();
const routeMock = { params: { token: 'raw-token' } };

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => routeMock,
  useRouter: () => ({ push: pushMock }),
}));

describe('CookbookInvitationView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let pinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    pinia = createPinia();
    setActivePinia(pinia);
  });

  it('displays an expired invitation state', async () => {
    fetchMock.mockResolvedValue({ ok: false, status: 410, json: async () => ({ success: false, error: { message: 'Cette invitation n’est plus valide.' } }) } as Response);
    const wrapper = mount(CookbookInvitationView, { global: { plugins: [pinia] } });
    await flushPromises();

    expect(wrapper.get('h2').text()).toBe('Invitation expirée');
    expect(wrapper.get('[role="alert"]').text()).toContain('n’est plus valide');
  });

  it('accepts a valid invitation for an authenticated user', async () => {
    useAuthStore(pinia).applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { id: 1, email: 'jane@example.com', role: 'viewer', expires_at: '2026-08-01T00:00:00Z', accepted_at: null, cookbook: { id: 'book', name: 'Famille' } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { invitation: { id: 1, accepted_at: '2026-07-23T00:00:00Z' }, cookbook: { id: 'book', name: 'Famille', role: 'viewer' } } }) } as Response);
    const wrapper = mount(CookbookInvitationView, { global: { plugins: [pinia] } });
    await flushPromises();
    await wrapper.get('.primary-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/invitations/token/raw-token/accept', expect.objectContaining({ method: 'POST' }));
    expect(wrapper.text()).toContain('Invitation acceptée');
    await wrapper.get('.primary-button').trigger('click');
    expect(pushMock).toHaveBeenCalledWith({ name: 'cookbook', params: { id: 'book' } });
  });
});
