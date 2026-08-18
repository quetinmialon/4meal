import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/auth';

import SearchView from '../SearchView.vue';

const mockRoute = vi.hoisted(() => ({ query: {} as Record<string, string> }));
const mockRouter = vi.hoisted(() => ({ replace: vi.fn(), push: vi.fn() }));

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>' },
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}));

describe('SearchView', () => {
  const fetchMock = vi.fn<typeof fetch>();
  let testPinia: ReturnType<typeof createPinia>;

  beforeEach(() => {
    vi.useFakeTimers();
    fetchMock.mockReset();
    mockRouter.replace.mockReset();
    mockRouter.push.mockReset();
    mockRoute.query = {};
    vi.stubGlobal('fetch', fetchMock);
    testPinia = createPinia();
    setActivePinia(testPinia);
    useAuthStore(testPinia).applySession({
      accessToken: 'jwt-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 7, name: 'Jane Doe', email: 'jane@example.com', avatar_path: null, last_login_at: null, created_at: null },
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  function page(data: unknown[], total = data.length, currentPage = 1, lastPage = 1) {
    return {
      ok: true,
      json: async () => ({
        success: true,
        data,
        meta: { pagination: { current_page: currentPage, per_page: 15, total, last_page: lastPage, from: 1, to: data.length, has_more_pages: currentPage < lastPage } },
      }),
    } as Response;
  }

  function cookbooksPage() {
    return {
      ok: true,
      json: async () => ({
        success: true,
        data: [{ id: 'cookbook-id', name: 'Menus de la semaine', owner: { id: 1, name: 'Jane Doe' }, member_role: 'owner' }],
        meta: { pagination: { current_page: 1, per_page: 15, total: 1, last_page: 1, from: 1, to: 1, has_more_pages: false } },
      }),
    } as Response;
  }

  function savedSearchesPage(data: unknown[] = []) {
    return {
      ok: true,
      json: async () => ({ success: true, data, meta: {} }),
    } as Response;
  }

  it('debounces the search, synchronizes q with the URL and displays results', async () => {
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage());
    fetchMock.mockResolvedValue(page([{ id: 'recipe-id', title: 'Soupe curry', description: null, prep_time_minutes: null, cook_time_minutes: null, servings: null, tags: [] }]));
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });

    await wrapper.get('#recipe-search').setValue('curry');
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(mockRouter.replace).toHaveBeenCalledWith({ query: { q: 'curry', page: undefined } });

    vi.advanceTimersByTime(349);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    vi.advanceTimersByTime(1);
    await flushPromises();

    expect(fetchMock).toHaveBeenLastCalledWith('/api/recipes?page=1&q=curry', {
      credentials: 'include', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.text()).toContain('Soupe curry');
  });

  it('shows the empty state without querying when no term is present', () => {
    fetchMock.mockResolvedValue(cookbooksPage());
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });

    expect(wrapper.text()).toContain('Que cherchez-vous ?');
    expect(fetchMock).toHaveBeenCalledWith('/api/cookbooks?page=1', expect.any(Object));
  });

  it('shows errors and retries the current search', async () => {
    mockRoute.query = { q: 'curry' };
    fetchMock.mockResolvedValueOnce(cookbooksPage())
      .mockResolvedValueOnce(savedSearchesPage())
      .mockResolvedValueOnce({ ok: false, status: 500, json: async () => ({ success: false, error: { message: 'Service indisponible' } }) } as Response)
      .mockResolvedValueOnce(page([{ id: 'recipe-id', title: 'Soupe curry' }]));
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });

    vi.advanceTimersByTime(350);
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('Service indisponible');

    await wrapper.get('[role="alert"] button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Soupe curry');
    expect(fetchMock).toHaveBeenCalledTimes(4);
  });

  it('keeps pagination in the URL', async () => {
    mockRoute.query = { q: 'curry' };
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage());
    fetchMock.mockResolvedValue(page([{ id: 'recipe-id', title: 'Soupe curry' }], 16, 1, 2));
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });

    vi.advanceTimersByTime(350);
    await flushPromises();
    await wrapper.get('[aria-label="Pagination des recettes"] button:last-child').trigger('click');

    expect(mockRouter.push).toHaveBeenCalledWith({ query: { q: 'curry', page: '2' } });
  });

  it('hydrates all filters from the URL and sends them to the API', async () => {
    mockRoute.query = {
      cookbook_id: 'cookbook-id', tag: 'rapide', ingredient: 'tomates',
      max_prep_time: '20', max_cook_time: '35', favorites: 'true',
    };
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage()).mockResolvedValueOnce(page([{ id: 'recipe-id', title: 'Soupe' }]));
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });
    await flushPromises();

    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('cookbook-id');
    expect((wrapper.get('input[placeholder="Ex. rapide"]').element as HTMLInputElement).value).toBe('rapide');
    expect((wrapper.get('input[placeholder="Ex. tomates"]').element as HTMLInputElement).value).toBe('tomates');
    expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(true);

    vi.advanceTimersByTime(350);
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/recipes?page=1&cookbook_id=cookbook-id&tag=rapide&ingredient=tomates&max_prep_time=20&max_cook_time=35&favorites=true', expect.any(Object));
  });

  it('synchronizes filter changes and resets them', async () => {
    fetchMock.mockResolvedValue(cookbooksPage());
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });
    await flushPromises();

    await wrapper.get('select').setValue('cookbook-id');
    expect(mockRouter.replace).toHaveBeenLastCalledWith({ query: expect.objectContaining({ cookbook_id: 'cookbook-id', page: undefined }) });

    await wrapper.get('.reset-button').trigger('click');
    expect(mockRouter.replace).toHaveBeenLastCalledWith({ query: {} });
    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('');
    expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(false);
  });

  it('opens the advanced filters drawer with an accessible name and focus target', async () => {
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage());
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] }, attachTo: document.body });
    await flushPromises();

    await wrapper.get('.advanced-filter-button').trigger('click');
    await flushPromises();

    const drawer = wrapper.get('#advanced-filters');
    expect(drawer.attributes('role')).toBe('dialog');
    expect(drawer.attributes('aria-labelledby')).toBe('advanced-filters-title');
    expect(drawer.attributes('tabindex')).toBe('-1');
    expect(wrapper.get('#advanced-filters-title').text()).toContain('Filtres avancés');
    expect(document.activeElement).toBe(drawer.element);
    wrapper.unmount();
  });

  it('shows loading state while filtered recipes are loading', async () => {
    let resolveRecipes: ((response: Response) => void) | undefined;
    const pendingRecipes = new Promise<Response>((resolve) => { resolveRecipes = resolve; });
    mockRoute.query = { favorites: 'true' };
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage()).mockReturnValueOnce(pendingRecipes);
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });

    vi.advanceTimersByTime(350);
    await flushPromises();
    expect(wrapper.get('[role="status"]').text()).toContain('Recherche en cours');
    resolveRecipes?.(page([]));
    await flushPromises();
  });

  it('loads a saved search and applies its criteria', async () => {
    const savedSearch = { id: 'saved-id', name: 'Dîners rapides', criteria: { q: 'curry', favorites: true }, created_at: null, updated_at: null };
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage([savedSearch]));
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });
    await flushPromises();

    await wrapper.get('.saved-search-load').trigger('click');
    expect(mockRouter.push).toHaveBeenCalledWith({ query: { q: 'curry', cookbook_id: undefined, tag: undefined, ingredient: undefined, max_prep_time: undefined, max_cook_time: undefined, favorites: 'true', min_rating: undefined, sort: undefined, page: undefined } });
  });

  it('creates and deletes saved searches through the API', async () => {
    const savedSearch = { id: 'saved-id', name: 'Dîners rapides', criteria: {}, created_at: null, updated_at: null };
    fetchMock.mockResolvedValueOnce(cookbooksPage()).mockResolvedValueOnce(savedSearchesPage())
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: savedSearch }) } as Response);
    const wrapper = mount(SearchView, { global: { plugins: [testPinia] } });
    await flushPromises();

    await wrapper.get('#saved-search-name').setValue('Dîners rapides');
    await wrapper.get('.save-search-form').trigger('submit');
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/saved-searches', {
      credentials: 'include',
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token', 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: 'Dîners rapides', criteria: {} }),
    });
    expect(wrapper.text()).toContain('Dîners rapides');

    fetchMock.mockResolvedValueOnce({ ok: true, status: 204, json: async () => null } as Response);
    await wrapper.get('.saved-search-delete').trigger('click');
    await flushPromises();
    expect(fetchMock).toHaveBeenLastCalledWith('/api/saved-searches/saved-id', {
      credentials: 'include', method: 'DELETE', headers: { Accept: 'application/json', Authorization: 'Bearer jwt-token' },
    });
    expect(wrapper.find('.saved-search-load').exists()).toBe(false);
  });
});
