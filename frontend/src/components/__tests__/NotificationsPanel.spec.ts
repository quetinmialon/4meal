import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import NotificationsPanel from '../NotificationsPanel.vue';

const pushMock = vi.fn();

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a href="#"><slot /></a>' },
  useRouter: () => ({ push: pushMock }),
}));

describe('NotificationsPanel', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    pushMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
  });

  const notification = {
    id: 'notification-id',
    type: 'cookbook_message' as const,
    data: {
      type: 'cookbook_message' as const,
      cookbook: { id: 'cookbook-id', name: 'Chez nous' },
      message: { id: 'message-id', preview: 'On mange quoi ce soir ?' },
      sender: { id: 12, name: 'Alex' },
    },
    read_at: null,
    created_at: '2026-08-04T10:00:00Z',
  };

  it('displays the unread counter and message notification', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: [notification], meta: { unread_count: 1 } }),
    } as Response);

    const wrapper = mount(NotificationsPanel, { props: { tokenType: 'Bearer', accessToken: 'jwt-token' } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/notifications?per_page=20', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.get('.notification-count').text()).toBe('1');
    expect(wrapper.text()).toContain('Alex a envoyé un message');
    expect(wrapper.text()).toContain('On mange quoi ce soir ?');
  });

  it('marks an unread notification as read before opening its cookbook', async () => {
    fetchMock
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: [notification], meta: { unread_count: 1 } }),
      } as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ success: true, data: { ...notification, read_at: '2026-08-04T10:01:00Z' } }),
      } as Response);

    const wrapper = mount(NotificationsPanel, { props: { tokenType: 'Bearer', accessToken: 'jwt-token' } });
    await flushPromises();
    await wrapper.get('.notification-item').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/notifications/notification-id/read', {
      credentials: 'include', method: 'PATCH', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(pushMock).toHaveBeenCalledWith({ name: 'cookbook-messages', params: { id: 'cookbook-id' } });
    expect(wrapper.find('.notification-count').exists()).toBe(false);
  });
});
