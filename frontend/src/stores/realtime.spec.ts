import { computed } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useRealtimeStore } from '@/stores/realtime';
import type { CookbookMessage } from '@/utils/cookbooks';
import type { RecipeComment } from '@/utils/recipes';

describe('realtime store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('updates a reactive cookbook message list without duplicating messages', () => {
    const store = useRealtimeStore();
    const messages = computed(() => store.messagesByCookbook.cookbook ?? []);
    const initial = { id: 'message-1', content: 'Initial' } as CookbookMessage;
    const updated = { id: 'message-1', content: 'Updated' } as CookbookMessage;

    store.setMessages('cookbook', [initial]);
    expect(messages.value).toEqual([initial]);

    store.upsertMessage('cookbook', updated);
    expect(messages.value).toEqual([updated]);
  });

  it('adds, updates, and removes comments from a reactive recipe list', () => {
    const store = useRealtimeStore();
    const comments = computed(() => store.commentsByRecipe.recipe ?? []);
    const created = { id: 'comment-1', content: 'Created' } as RecipeComment;
    const updated = { id: 'comment-1', content: 'Updated' } as RecipeComment;

    store.receiveComment({ recipe: { id: 'recipe' }, comment: created });
    expect(comments.value).toEqual([created]);

    store.receiveComment({ recipe: { id: 'recipe' }, comment: updated });
    expect(comments.value).toEqual([updated]);

    store.removeComment({ recipe: { id: 'recipe' }, comment: { id: 'comment-1' } });
    expect(comments.value).toEqual([]);
  });
});
