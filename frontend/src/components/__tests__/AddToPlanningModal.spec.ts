import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import AddToPlanningModal from '../AddToPlanningModal.vue';

describe('AddToPlanningModal', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  const recipe = {
    id: 'recipe-id', title: 'Soupe de légumes', slug: null, description: null, prep_time_minutes: null,
    cook_time_minutes: null, servings: 4, source: null, author: null, created_at: null,
  };

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  it('confirms a personal planning entry with the selected date and meal type', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {} } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { id: 'planned-id' } }) } as Response);

    const added = vi.fn();
    const wrapper = mount(AddToPlanningModal, { props: { recipe, onAdded: added }, global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#planning-date').setValue('2026-08-10');
    await wrapper.get('#planning-meal-type').setValue('lunch');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/planned-meals', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ recipe_id: 'recipe-id', date: '2026-08-10', meal_type: 'lunch', cookbook_id: null }),
    }));
    expect(added).toHaveBeenCalledOnce();
  });

  it('requires a cookbook when the cookbook destination is selected', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {} } }) } as Response);

    const wrapper = mount(AddToPlanningModal, { props: { recipe }, global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.find('input[type="radio"][value="cookbook"]').setValue();
    await wrapper.get('form').trigger('submit');

    expect(wrapper.get('[role="alert"]').text()).toContain('Choisissez un cookbook');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('asks for confirmation and submits a weekly series with its end date', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {} } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { id: 'planned-id' } }) } as Response);

    const added = vi.fn();
    const wrapper = mount(AddToPlanningModal, { props: { recipe, onAdded: added }, global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#planning-date').setValue('2026-08-10');
    await wrapper.get('#planning-recurrence-frequency').setValue('weekly');
    await wrapper.get('#planning-recurrence-until').setValue('2026-08-24');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.text()).toContain('Confirmer la série');
    expect(fetchMock).toHaveBeenCalledTimes(1);
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/planned-meals', expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({ recipe_id: 'recipe-id', date: '2026-08-10', meal_type: 'dinner', cookbook_id: null, recurrence: { frequency: 'weekly', until: '2026-08-24' } }),
    }));
    expect(added).toHaveBeenCalledOnce();
  });

  it('requires a series end date when repetition is enabled', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {} } }) } as Response);

    const wrapper = mount(AddToPlanningModal, { props: { recipe }, global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('#planning-recurrence-frequency').setValue('weekly');
    await wrapper.get('form').trigger('submit');

    expect(wrapper.get('[role="alert"]').text()).toContain('fin de la série');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('shows API errors and keeps the modal open', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: {} } }) } as Response)
      .mockResolvedValueOnce({
        ok: false, status: 422,
        json: async () => ({ success: false, error: { message: 'Date invalide.', details: { fields: { date: ['La date est invalide.'] } } } }),
      } as Response);

    const wrapper = mount(AddToPlanningModal, { props: { recipe }, global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Date invalide.');
    expect(wrapper.text()).toContain('La date est invalide.');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
  });
});
