import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import AppLayout from '../AppLayout.vue';

const mockRoute = vi.hoisted(() => ({
  name: 'dashboard' as string,
  fullPath: '/dashboard',
  params: {} as Record<string, string>,
}));
const mockRouter = vi.hoisted(() => ({ push: vi.fn() }));

vi.mock('vue-router', () => ({
  RouterLink: defineComponent({
    props: { to: { type: [String, Object], required: true } },
    setup(props, { slots }) {
      return () => {
        const to = props.to as { name?: string; params?: Record<string, string> };
        return h('a', { 'data-route': to.name ?? String(props.to), 'data-id': to.params?.id }, slots.default?.());
      };
    },
  }),
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}));

describe('AppLayout', () => {
  beforeEach(() => {
    mockRoute.name = 'dashboard';
    mockRoute.fullPath = '/dashboard';
    mockRoute.params = {};
    mockRouter.push.mockReset();
    document.body.style.overflow = '';
    setActivePinia(createPinia());
    useAuthStore().applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  function mountLayout() {
    return mount(AppLayout, {
      slots: { default: '<h1>Contenu</h1>' },
      global: { stubs: { NotificationsPanel: true } },
      attachTo: document.body,
    });
  }

  it('exposes the main navigation and closes the quick menu with Escape', async () => {
    const wrapper = mountLayout();

    expect(wrapper.get('nav.sidebar-navigation').text()).toContain('Recettes');
    expect(wrapper.get('.header-search a').attributes('data-route')).toBe('search');

    await wrapper.get('.header-action').trigger('click');
    expect(wrapper.get('[role="menu"]')).toBeTruthy();
    expect(wrapper.get('.header-action').attributes('aria-expanded')).toBe('true');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    await nextTick();

    expect(wrapper.find('[role="menu"]').exists()).toBe(false);
    expect(wrapper.get('.header-action').attributes('aria-expanded')).toBe('false');
    wrapper.unmount();
  });

  it('opens and closes the mobile navigation while managing body scroll and focus', async () => {
    const wrapper = mountLayout();
    const trigger = wrapper.get('.mobile-menu-button');

    await trigger.trigger('click');
    await nextTick();

    expect(wrapper.get('.sidebar').classes()).toContain('is-open');
    expect(wrapper.get('.mobile-menu-button').attributes('aria-expanded')).toBe('true');
    expect(wrapper.find('.navigation-scrim').exists()).toBe(true);
    expect(document.body.style.overflow).toBe('hidden');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    await nextTick();

    expect(wrapper.get('.sidebar').classes()).not.toContain('is-open');
    expect(wrapper.get('.mobile-menu-button').attributes('aria-expanded')).toBe('false');
    expect(document.body.style.overflow).toBe('');
    expect(document.activeElement).toBe(wrapper.get('.mobile-menu-button').element);
    wrapper.unmount();
  });

  it('uses the dedicated cookbook settings route and marks the active tab', () => {
    mockRoute.name = 'cookbook-settings';
    mockRoute.fullPath = '/cookbooks/book-1/parametres';
    mockRoute.params = { id: 'book-1' };
    const wrapper = mountLayout();

    const settingsLink = wrapper.get('nav[aria-label="Navigation du cookbook"] a[data-route="cookbook-settings"]');
    expect(settingsLink.attributes('data-id')).toBe('book-1');
    expect(settingsLink.attributes('aria-current')).toBe('page');
    wrapper.unmount();
  });
});
