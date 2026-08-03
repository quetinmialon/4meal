import type { ShoppingListItem } from '@/utils/planning';

export type EditableShoppingListItem = ShoppingListItem & { id: string; checked: boolean };

type UnitInfo = { family: string; factor: number };

function normalize(value: string): string {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLocaleLowerCase('fr-FR');
}

function unitInfo(unit: string | null): UnitInfo {
  const value = normalize(unit ?? '');
  if (['mg', 'milligramme', 'milligrammes'].includes(value)) return { family: 'mass', factor: 0.001 };
  if (['g', 'gramme', 'grammes'].includes(value)) return { family: 'mass', factor: 1 };
  if (['kg', 'kilogramme', 'kilogrammes'].includes(value)) return { family: 'mass', factor: 1000 };
  if (['ml', 'millilitre', 'millilitres'].includes(value)) return { family: 'volume', factor: 1 };
  if (['cl', 'centilitre', 'centilitres'].includes(value)) return { family: 'volume', factor: 10 };
  if (['l', 'litre', 'litres'].includes(value)) return { family: 'volume', factor: 1000 };
  if (['piece', 'pieces', 'unite', 'unites', 'pcs'].includes(value)) return { family: 'count', factor: 1 };
  return { family: `unit:${value}`, factor: 1 };
}

export function groupShoppingItems(items: ShoppingListItem[]): EditableShoppingListItem[] {
  const result: EditableShoppingListItem[] = [];
  const indexes = new Map<string, number>();

  items.forEach((item, sourceIndex) => {
    const info = unitInfo(item.unit);
    const key = item.quantity === null ? null : `${normalize(item.name)}|${info.family}`;
    const existingIndex = key === null ? undefined : indexes.get(key);

    if (existingIndex !== undefined) {
      const existing = result[existingIndex];
      if (existing) {
        const convertedQuantity = (item.quantity ?? 0) * info.factor / unitInfo(existing.unit).factor;
        existing.quantity = Math.round(((existing.quantity ?? 0) + convertedQuantity) * 1000) / 1000;
        existing.is_optional = existing.is_optional && item.is_optional;
        return;
      }
    }

    const next: EditableShoppingListItem = { ...item, id: `shopping-item-${sourceIndex}`, checked: false };
    if (key !== null) indexes.set(key, result.length);
    result.push(next);
  });

  return result;
}
