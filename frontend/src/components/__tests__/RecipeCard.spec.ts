import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import RecipeCard from '../RecipeCard.vue';

describe('RecipeCard', () => {
  it('displays the recipe image when one is available', () => {
    const wrapper = mount(RecipeCard, {
      props: {
        recipe: {
          id: 'recipe-id',
          title: 'Tarte aux pommes',
          slug: 'tarte-aux-pommes',
          description: null,
          prep_time_minutes: null,
          cook_time_minutes: null,
          servings: null,
          source: null,
          created_at: null,
          author: null,
          image_url: '/storage/recipes/tarte.jpg',
        },
      },
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    });

    expect(wrapper.get('img').attributes('src')).toBe('/storage/recipes/tarte.jpg');
    expect(wrapper.get('img').attributes('alt')).toBe('Photo de Tarte aux pommes');
  });

  it('does not render an empty image element without a URL', () => {
    const wrapper = mount(RecipeCard, {
      props: {
        recipe: {
          id: 'recipe-id', title: 'Sans image', slug: null, description: null,
          prep_time_minutes: null, cook_time_minutes: null, servings: null,
          source: null, created_at: null, author: null,
        },
      },
      global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
    });

    expect(wrapper.find('img').exists()).toBe(false);
  });
});
