import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import PlanningView from '../PlanningView.vue';

describe('PlanningView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  const meal = {
    id: 'meal-id', date: '2026-07-28', meal_type: 'dinner', note: 'Prévoir du pain', initial_servings: 4, servings: 4,
    cookbook_id: null, created_at: null,
    recipe: { id: 'recipe-id', title: 'Ratatouille', slug: 'ratatouille', servings: 4, image_url: null, ingredients: [
      { position: 1, name: 'Tomates', quantity: 150, unit: 'g', preparation: null, is_optional: false, group_name: null },
      { position: 2, name: 'Sel', quantity: null, unit: null, preparation: 'à votre goût', is_optional: true, group_name: null },
    ] },
  } as const;

  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-07-28T12:00:00'));
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  afterEach(() => { vi.useRealTimers(); });

  function successResponse(data: unknown[]) {
    return { ok: true, json: async () => ({ success: true, data }) } as Response;
  }

  it('loads the current week and navigates to the next week', async () => {
    fetchMock.mockResolvedValue(successResponse([meal]));
    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith('/api/planned-meals?from=2026-07-27&to=2026-08-02', expect.any(Object));
    expect(wrapper.text()).toContain('Ratatouille');

    await wrapper.get('[aria-label="Période suivante"]').trigger('click');
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/planned-meals?from=2026-08-03&to=2026-08-09', expect.any(Object));
  });

  it('switches to month mode and opens the meal detail', async () => {
    fetchMock.mockResolvedValue(successResponse([meal]));
    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    await flushPromises();

    await wrapper.get('.view-switcher button:nth-child(2)').trigger('click');
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/planned-meals?from=2026-07-01&to=2026-07-31', expect.any(Object));
    expect(wrapper.findAll('.calendar-day')).toHaveLength(42);

    await wrapper.get('.meal-card').trigger('click');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Ratatouille');
    expect(wrapper.get('[role="dialog"]').text()).toContain('150 g Tomates');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Sel');
    expect(wrapper.get('[role="dialog"]').text()).toContain('Prévoir du pain');
    await wrapper.get('[aria-label="Fermer le détail"]').trigger('click');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
  });

  it('shows loading, empty and error states', async () => {
    let resolveRequest!: (response: Response) => void;
    fetchMock.mockReturnValueOnce(new Promise((resolve) => { resolveRequest = resolve; }));
    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    expect(wrapper.get('[role="status"]').text()).toContain('Chargement');

    resolveRequest(successResponse([]));
    await flushPromises();
    expect(wrapper.text()).toContain('Aucun repas planifié');

    fetchMock.mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({ success: false, error: { message: 'Planning indisponible' } }) } as Response);
    await wrapper.get('[aria-label="Période suivante"]').trigger('click');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('Planning indisponible');
  });

  it('edits a meal and refreshes the current period after success', async () => {
    fetchMock
      .mockResolvedValueOnce(successResponse([meal]))
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: { ...meal, date: '2026-07-29', meal_type: 'lunch', note: 'Nouvelle note' } }) } as Response)
      .mockResolvedValueOnce(successResponse([{ ...meal, date: '2026-07-29', meal_type: 'lunch', note: 'Nouvelle note' }]));

    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.meal-card').trigger('click');
    await wrapper.get('.edit-detail-button').trigger('click');
    await wrapper.get('#edit-meal-date').setValue('2026-07-29');
    await wrapper.get('#edit-meal-type').setValue('lunch');
    await wrapper.get('#edit-meal-note').setValue('Nouvelle note');
    await wrapper.get('#edit-meal-servings').setValue(6);
    await wrapper.get('.edit-meal-form').trigger('submit');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/planned-meals/meal-id', expect.objectContaining({
      method: 'PATCH',
      body: JSON.stringify({ date: '2026-07-29', meal_type: 'lunch', note: 'Nouvelle note', servings: 6 }),
    }));
    expect(fetchMock).toHaveBeenNthCalledWith(3, '/api/planned-meals?from=2026-07-27&to=2026-08-02', expect.any(Object));
    expect(wrapper.get('[role="status"]').text()).toContain('modifié');
  });

  it('requires confirmation before deleting and refreshes after confirmation', async () => {
    fetchMock
      .mockResolvedValueOnce(successResponse([meal]))
      .mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response)
      .mockResolvedValueOnce(successResponse([]));

    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.meal-card').trigger('click');
    await wrapper.get('.delete-detail-button').trigger('click');
    expect(wrapper.text()).toContain('Supprimer ce repas planifié');
    expect(fetchMock).toHaveBeenCalledTimes(1);

    await wrapper.get('.delete-confirmation .delete-detail-button').trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/planned-meals/meal-id', expect.objectContaining({ method: 'DELETE' }));
    expect(fetchMock).toHaveBeenNthCalledWith(3, '/api/planned-meals?from=2026-07-27&to=2026-08-02', expect.any(Object));
    expect(wrapper.text()).toContain('supprimé');
  });

  it('keeps the edit form open and shows API errors', async () => {
    fetchMock
      .mockResolvedValueOnce(successResponse([meal]))
      .mockResolvedValueOnce({
        ok: false, status: 422,
        json: async () => ({ success: false, error: { message: 'Date invalide.', details: { fields: { date: ['La date est invalide.'] } } } }),
      } as Response);

    const wrapper = mount(PlanningView, { global: { plugins: [testPinia] } });
    await flushPromises();
    await wrapper.get('.meal-card').trigger('click');
    await wrapper.get('.edit-detail-button').trigger('click');
    await wrapper.get('.edit-meal-form').trigger('submit');
    await flushPromises();

    expect(wrapper.get('.edit-meal-form').text()).toContain('Date invalide.');
    expect(wrapper.get('.edit-meal-form').text()).toContain('La date est invalide.');
  });
});
