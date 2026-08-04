import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import ImportView from '../ImportView.vue';

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
}));

describe('ImportView', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    setActivePinia(createPinia());
    useAuthStore().applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  function chooseFile(wrapper: ReturnType<typeof mount>, file: File): void {
    const input = wrapper.get('input[type="file"]').element;
    Object.defineProperty(input, 'files', { configurable: true, value: [file] });
    void wrapper.get('input[type="file"]').trigger('change');
  }

  it('shows file selection, warnings and keeps import disabled without a file', () => {
    const wrapper = mount(ImportView);

    expect(wrapper.text()).toContain('Avant de commencer');
    expect(wrapper.text()).toContain('identifiants externes');
    expect((wrapper.get('button.import-button').element as HTMLButtonElement).disabled).toBe(true);
  });

  it('uploads the selected JSON and displays the structured result', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: { report: { cookbooks: 2, recipes: 5, duplicates: [{ path: 'recipes.1', type: 'recipe', reason: 'Déjà présente.' }] } },
      }),
    } as Response);
    const wrapper = mount(ImportView);
    chooseFile(wrapper, new File(['{}'], 'backup.json', { type: 'application/json' }));
    await flushPromises();

    expect(wrapper.text()).toContain('backup.json');
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    const request = fetchMock.mock.calls[0]!;
    expect(request[0]).toBe('/api/import');
    expect(request[1]).toMatchObject({ method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' } });
    expect((request[1] as RequestInit).body).toBeInstanceOf(FormData);
    expect(wrapper.text()).toContain('Import terminé');
    expect(wrapper.text()).toContain('Cookbooks importés');
    expect(wrapper.text()).toContain('Déjà présente.');
  });

  it('displays safe structured API errors', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 422,
      json: async () => ({
        success: false,
        error: {
          message: 'Le document contient des incohérences métier.',
          details: { errors: [{ path: 'recipes.0.cookbook_ids.0', code: 'unknown_reference', message: 'Référence inconnue.' }] },
        },
      }),
    } as Response);
    const wrapper = mount(ImportView);
    chooseFile(wrapper, new File(['{}'], 'backup.json', { type: 'application/json' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    expect(wrapper.get('[role="alert"]').text()).toContain('recipes.0.cookbook_ids.0');
    expect(wrapper.get('[role="alert"]').text()).toContain('unknown_reference');
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

  it('imports a CSV recipe file and shows the CSV limitations', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: { report: { recipes: 2, duplicates: [] } } }),
    } as Response);
    const wrapper = mount(ImportView);
    await wrapper.get('input[type="radio"][value="csv"]').setValue(true);
    expect(wrapper.text()).toContain('pas de cookbooks, images');
    chooseFile(wrapper, new File(['format_version,record_type'], 'recipes.csv', { type: 'text/csv' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    const request = fetchMock.mock.calls[0]!;
    expect(request[0]).toBe('/api/import/csv');
    expect(wrapper.text()).toContain('Recettes importées');
  });

  it('rejects a non-CSV file before sending it in CSV mode', async () => {
    const wrapper = mount(ImportView);
    await wrapper.get('input[type="radio"][value="csv"]').setValue(true);
    chooseFile(wrapper, new File(['text'], 'backup.txt', { type: 'text/plain' }));
    await flushPromises();
    await wrapper.get('button.import-button').trigger('click');
    await flushPromises();

    expect(fetchMock).not.toHaveBeenCalled();
    expect(wrapper.get('[role="alert"]').text()).toContain('extension .csv');
  });
});
