import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CookbookMessageItem from '../CookbookMessageItem.vue';

describe('CookbookMessageItem', () => {
  const fetchMock = vi.fn<typeof fetch>();
  const message = {
    id: 'message-1', content: 'Bonjour', is_deleted: false, edited_at: null, deleted_at: null, deleted_by: null, created_at: null,
    author: { id: 2, name: 'Membre', avatar_url: null, role: 'commenter' }, reactions: [{ emoji: '👍', count: 2, reacted: true }],
  };

  beforeEach(() => { fetchMock.mockReset(); vi.stubGlobal('fetch', fetchMock); });

  it('edits the author message', async () => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { ...message, content: 'Modifié', edited_at: '2026-07-30T10:00:00Z' } }) } as Response);
    const wrapper = mount(CookbookMessageItem, { props: { message, cookbookId: 'cookbook-1', currentUserId: 2, currentUserRole: 'commenter', tokenType: 'Bearer', accessToken: 'jwt' } });
    await wrapper.findAll('button').find((button) => button.text() === 'Modifier')!.trigger('click');
    await wrapper.get('textarea').setValue(' Modifié ');
    await wrapper.get('form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks/cookbook-1/messages/message-1', expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ content: 'Modifié' }) }));
    expect(wrapper.emitted('updated')).toHaveLength(1);
  });

  it('requires confirmation before deletion and emits the tombstone', async () => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { ...message, is_deleted: true, content: 'Message supprimé par Membre', deleted_by: { id: 2, name: 'Membre' } } }) } as Response);
    const wrapper = mount(CookbookMessageItem, { props: { message, cookbookId: 'cookbook-1', currentUserId: 2, currentUserRole: 'commenter', tokenType: 'Bearer', accessToken: 'jwt' } });
    await wrapper.findAll('button').find((button) => button.text() === 'Supprimer')!.trigger('click');
    expect(wrapper.text()).toContain('Supprimer ce message ?');
    expect(fetchMock).not.toHaveBeenCalled();
    await wrapper.findAll('button').find((button) => button.text() === 'Confirmer')!.trigger('click');
    await flushPromises();
    expect(wrapper.emitted('deleted')).toHaveLength(1);
  });

  it('hides moderation actions from another regular member', () => {
    const wrapper = mount(CookbookMessageItem, { props: { message, cookbookId: 'cookbook-1', currentUserId: 9, currentUserRole: 'commenter', tokenType: 'Bearer', accessToken: 'jwt' } });
    expect(wrapper.text()).not.toContain('Modifier');
    expect(wrapper.text()).not.toContain('Supprimer');
  });

  it('offers an accessible emoji picker and toggles a reaction counter', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true, status: 201, json: async () => ({ success: true, data: { emoji: '❤️' } }) } as Response);
    const wrapper = mount(CookbookMessageItem, { props: { message, cookbookId: 'cookbook-1', currentUserId: 9, currentUserRole: 'commenter', tokenType: 'Bearer', accessToken: 'jwt' } });

    expect(wrapper.get('details summary').text()).toBe('Ajouter une réaction');
    expect(wrapper.findAll('.reaction-option')).toHaveLength(6);
    expect(wrapper.findAll('.reaction-option').find((button) => button.attributes('aria-label')?.includes('😂'))!.attributes('aria-label')).toContain('Ajouter la réaction');
    expect(wrapper.get('.reaction-counter').attributes('aria-pressed')).toBe('true');

    await wrapper.findAll('.reaction-option').find((button) => button.attributes('aria-label')?.includes('❤️'))!.trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks/cookbook-1/messages/message-1/reactions', expect.objectContaining({ method: 'POST', body: JSON.stringify({ emoji: '❤️' }) }));
    expect(wrapper.text()).toContain('❤️ 1');
    expect(wrapper.findAll('.reaction-counter')).toHaveLength(2);
  });

  it('rolls back an optimistic reaction and announces the API error', async () => {
    fetchMock.mockResolvedValueOnce({ ok: false, status: 403, json: async () => ({ success: false, error: { message: 'Action interdite' } }) } as Response);
    const wrapper = mount(CookbookMessageItem, { props: { message, cookbookId: 'cookbook-1', currentUserId: 9, currentUserRole: 'commenter', tokenType: 'Bearer', accessToken: 'jwt' } });

    await wrapper.get('.reaction-counter').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toBe('Action interdite');
    expect(wrapper.get('.reaction-counter').attributes('aria-pressed')).toBe('true');
  });
});
