import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ImportView from '../ImportView.vue';

vi.mock('vue-router', () => ({ RouterLink: { template: '<a><slot /></a>' } }));

describe('ImportView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    setActivePinia(createPinia());
    useAuthStore().applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  function chooseFile(wrapper: ReturnType<typeof mount>, file: File): void {
    const input = wrapper.get('input[type="file"]').element;
    Object.defineProperty(input, 'files', { configurable: true, value: [file] });
    void wrapper.get('input[type="file"]').trigger('change');
  }

  it('shows file selection and keeps import disabled without a file', () => {
    const wrapper = mount(ImportView);
    expect(wrapper.text()).toContain('Avant de commencer');
    expect((wrapper.get('button.import-button').element as HTMLButtonElement).disabled).toBe(true);
  });

  it('previews JSON objects and imports only after confirmation', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { analysis: {
        objects: [{ path: 'recipes.0', type: 'recipe', id: 'r1', title: 'Omelette' }], warnings: [], errors: [], duplicates: [],
      } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { report: { recipes: 1, duplicates: [] } } }) } as Response);
    const wrapper = mount(ImportView);
    chooseFile(wrapper, new File(['{}'], 'backup.json', { type: 'application/json' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/import/preview');
    expect(wrapper.text()).toContain('Prévisualisation avant import');
    expect(wrapper.text()).toContain('Omelette');
    expect(fetchMock).toHaveBeenCalledTimes(1);

    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();
    expect(fetchMock.mock.calls[1]?.[0]).toBe('/api/import');
    expect(wrapper.text()).toContain('Import terminé');
  });

  it('displays preview errors and disables final confirmation', async () => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ success: true, data: { analysis: {
      objects: [], warnings: [], errors: [{ path: 'recipes.0', code: 'schema_invalid', message: 'Structure invalide.' }], duplicates: [],
    } } }) } as Response);
    const wrapper = mount(ImportView);
    chooseFile(wrapper, new File(['{}'], 'backup.json', { type: 'application/json' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('Structure invalide.');
    expect(wrapper.get('button.import-button').text()).toContain('Confirmer et importer');
    expect((wrapper.get('button.import-button').element as HTMLButtonElement).disabled).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('rejects a non-JSON file before sending it', async () => {
    const wrapper = mount(ImportView);
    chooseFile(wrapper, new File(['text'], 'backup.txt', { type: 'text/plain' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();
    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('[role="alert"]').text()).toContain('extension .json');
  });

  it('keeps CSV and Mealie import flows available', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { analysis: { objects: [{ path: 'recipes.0', type: 'recipe', id: 'r1', title: 'Soupe' }], warnings: [], errors: [], duplicates: [] } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { report: { recipes: 1, duplicates: [] } } }) } as Response);
    const wrapper = mount(ImportView);
    await wrapper.get('input[type="radio"][value="csv"]').setValue(true);
    chooseFile(wrapper, new File(['format_version,record_type'], 'recipes.csv', { type: 'text/csv' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/import/preview/csv');
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();
    expect(fetchMock.mock.calls[1]?.[0]).toBe('/api/import/csv');
  });
});
