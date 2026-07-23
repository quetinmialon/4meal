import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import CookbookInvitationForm from '../CookbookInvitationForm.vue';

describe('CookbookInvitationForm', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let pinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    pinia = createPinia();
    setActivePinia(pinia);
    useAuthStore(pinia).applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  it('sends an invitation with the selected role and confirms success', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: { id: 1, email: 'invite@example.com', role: 'editor', expires_at: '2026-08-01T00:00:00Z', accepted_at: null, cookbook: { id: 'book', name: 'Famille' } } }),
    } as Response);
    const wrapper = mount(CookbookInvitationForm, { props: { cookbookId: 'book' }, global: { plugins: [pinia] } });

    await wrapper.get('#invitation-email').setValue('Invite@Example.com');
    await wrapper.get('#invitation-role').setValue('editor');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks/book/invitations', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ email: 'invite@example.com', role: 'editor' }),
    }));
    expect(wrapper.text()).toContain('Invitation envoyée à invite@example.com.');
  });

  it('displays validation and active-member errors', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 409,
      json: async () => ({ success: false, error: { message: 'Cet utilisateur est déjà membre actif.' } }),
    } as Response);
    const wrapper = mount(CookbookInvitationForm, { props: { cookbookId: 'book' }, global: { plugins: [pinia] } });
    await wrapper.get('#invitation-email').setValue('member@example.com');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('déjà membre actif');
  });
});
