import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ShoppingListView from '../ShoppingListView.vue';

describe('ShoppingListView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let printMock: ReturnType<typeof vi.spyOn>;
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    printMock = vi.spyOn(window, 'print').mockImplementation(() => undefined);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  afterEach(() => { printMock.mockRestore(); });

  it('loads a selected period, displays grouped items and allows checking/editing', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: [
        { name: 'Farine', quantity: 200, unit: 'g', preparation: null, is_optional: false },
        { name: 'farine', quantity: 0.5, unit: 'kg', preparation: null, is_optional: false },
      ] }),
    } as Response);

    const wrapper = mount(ShoppingListView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith(expect.stringMatching(/\/api\/planned-meals\/shopping-list\?from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2}/), expect.any(Object));
    expect(wrapper.findAll('.shopping-item')).toHaveLength(1);
    expect((wrapper.get('#shopping-item-0-quantity').element as HTMLInputElement).value).toBe('700');

    await wrapper.get('input[type="checkbox"]').setValue(true);
    expect(wrapper.get('.shopping-item').classes()).toContain('checked');
    await wrapper.get('.item-name').setValue('Farine complète');
    expect((wrapper.get('.item-name').element as HTMLInputElement).value).toBe('Farine complète');
  });

  it('reloads a new period and prints the editable list', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [] }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [{ name: 'Œufs', quantity: 4, unit: 'pièce', preparation: null, is_optional: false }] }) } as Response);

    const wrapper = mount(ShoppingListView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#shopping-from').setValue('2026-08-03');
    await wrapper.get('#shopping-to').setValue('2026-08-09');
    await wrapper.get('.period-form').trigger('submit');
    await flushPromises();

    expect(fetchMock).toHaveBeenLastCalledWith('/api/planned-meals/shopping-list?from=2026-08-03&to=2026-08-09', expect.any(Object));
    expect((wrapper.get('.item-name').element as HTMLInputElement).value).toBe('Œufs');
    await wrapper.get('.print-button').trigger('click');
    expect(printMock).toHaveBeenCalledOnce();
  });
});
