import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import RecipeCommentsSection from '../RecipeCommentsSection.vue';

describe('RecipeCommentsSection', () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
  });

  const pagination = { current_page: 1, per_page: 20, total: 1, last_page: 1, from: 1, to: 1, has_more_pages: false };
  const comment = {
    id: 'comment-1', content: 'Très bon !', edited_at: null, created_at: '2026-07-28T10:00:00Z',
    author: { id: 7, name: 'Jane Doe', avatar_url: null, role: 'commenter' },
  };

  it('displays a paginated comment and its author details', async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, data: [comment], meta: { pagination } }),
    } as Response);

    const wrapper = mount(RecipeCommentsSection, { props: { recipeId: 'recipe-1', tokenType: 'Bearer', accessToken: 'jwt' } });
    await flushPromises();

    expect(wrapper.text()).toContain('Très bon !');
    expect(wrapper.text()).toContain('Jane Doe');
    expect(wrapper.text()).toContain('Commentateur');
    expect(fetchMock).toHaveBeenCalledWith('/api/recipes/recipe-1/comments?per_page=20&page=1', {
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt' },
    });
  });

  it('shows the empty state and adds a comment', async () => {
    fetchMock
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [], meta: { pagination: { ...pagination, total: 0, from: null, to: null } } }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: comment }) } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ success: true, data: [comment], meta: { pagination } }) } as Response);

    const wrapper = mount(RecipeCommentsSection, { props: { recipeId: 'recipe-1', tokenType: 'Bearer', accessToken: 'jwt' } });
    await flushPromises();
    expect(wrapper.text()).toContain('Aucun commentaire pour le moment.');

    await wrapper.get('#recipe-comment-content').setValue('  Très bon !  ');
    await wrapper.get('.comment-form').trigger('submit.prevent');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/recipes/recipe-1/comments', {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: 'Bearer jwt', 'Content-Type': 'application/json' },
      body: JSON.stringify({ content: 'Très bon !' }),
    });
    expect(wrapper.text()).toContain('Très bon !');
  });

  it('shows API errors and validates an empty comment', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: { message: 'Commentaires indisponibles.' } }),
    } as Response);

    const wrapper = mount(RecipeCommentsSection, { props: { recipeId: 'recipe-1', tokenType: 'Bearer', accessToken: 'jwt' } });
    await flushPromises();
    expect(wrapper.text()).toContain('Commentaires indisponibles.');

    await wrapper.get('#recipe-comment-content').setValue('   ');
    await wrapper.get('.comment-form').trigger('submit.prevent');
    expect(wrapper.text()).toContain('Le commentaire est requis.');
  });
});
