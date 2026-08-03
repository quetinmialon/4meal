import { describe, expect, it } from 'vitest';

import { groupShoppingItems } from '@/utils/shoppingList';

describe('groupShoppingItems', () => {
  it('groups names case-insensitively and converts compatible units', () => {
    const items = groupShoppingItems([
      { name: 'Farine', quantity: 200, unit: 'g', preparation: null, is_optional: false },
      { name: 'farine', quantity: 0.5, unit: 'kg', preparation: null, is_optional: false },
    ]);

    expect(items).toHaveLength(1);
    expect(items[0]).toMatchObject({ name: 'Farine', quantity: 700, unit: 'g' });
  });

  it('keeps incompatible and unknown quantities separate', () => {
    const items = groupShoppingItems([
      { name: 'Lait', quantity: 200, unit: 'ml', preparation: null, is_optional: false },
      { name: 'Lait', quantity: 1, unit: 'pièce', preparation: null, is_optional: false },
      { name: 'Sel', quantity: null, unit: null, preparation: null, is_optional: false },
      { name: 'Sel', quantity: null, unit: null, preparation: null, is_optional: false },
    ]);

    expect(items).toHaveLength(4);
  });
});
