import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ExportView from '../ExportView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}));

describe('ExportView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(() => 'blob:export'), revokeObjectURL: vi.fn() });
    vi.stubGlobal('Blob', Blob);
    setActivePinia(createPinia());
    useAuthStore().applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  it('explains the exported data and requires explicit confirmation', () => {
    const wrapper = mount(ExportView);

    expect(wrapper.text()).toContain('Ce qui sera exporté');
    expect(wrapper.text()).toContain('lisible en clair');
    expect((wrapper.get('button').element as HTMLButtonElement).disabled).toBe(true);
  });

  it('downloads the export after confirmation and reports success', async () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(['{"format":"SUPMEAL"}'], { type: 'application/json' }),
      headers: new Headers({ 'Content-Disposition': 'attachment; filename=4meal-export-20260731-120000.json' }),
    } as Response);
    const wrapper = mount(ExportView);

    await wrapper.get('input[type="checkbox"]').setValue(true);
    await wrapper.get('button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/export', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(click).toHaveBeenCalled();
    expect(wrapper.text()).toContain('a été téléchargé');
    click.mockRestore();
  });

  it('displays API errors and does not download', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: { message: 'Export temporairement indisponible.' } }),
    } as Response);
    const wrapper = mount(ExportView);

    await wrapper.get('input[type="checkbox"]').setValue(true);
    await wrapper.get('button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('Export temporairement indisponible.');
  });

  it('downloads the CSV recipes export and explains its limits', async () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(['format_version,record_type'], { type: 'text/csv' }),
      headers: new Headers({ 'Content-Disposition': 'attachment; filename=4meal-recipes-20260731-120000.csv' }),
    } as Response);
    const wrapper = mount(ExportView);

    expect(wrapper.text()).toContain('Limites CSV');
    await wrapper.get('input[type="checkbox"]').setValue(true);
    await wrapper.get('button.secondary-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/export/csv', {
      credentials: 'include', headers: { Accept: 'text/csv', Authorization: 'Bearer jwt-token' },
    });
    expect(click).toHaveBeenCalled();
    click.mockRestore();
  });
});
