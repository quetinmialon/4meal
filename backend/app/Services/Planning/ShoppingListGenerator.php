<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use App\Models\RecipeIngredient;
use Illuminate\Support\Str;

class ShoppingListGenerator
{
    public function __construct(private readonly PlannedMealIngredientCalculator $calculator) {}

    /**
     * @param  iterable<PlannedMeal>  $meals
     * @return list<array{name:string, quantity:float|null, unit:string|null, preparation:string|null, is_optional:bool}>
     */
    public function generate(iterable $meals): array
    {
        $items = [];
        $indexes = [];

        foreach ($meals as $meal) {
            $recipe = $meal->getRelationValue('recipe');

            if ($recipe === null) {
                continue;
            }

            foreach ($recipe->ingredients as $ingredient) {
                /** @var RecipeIngredient $ingredient */
                $quantity = $this->calculator->quantity($meal, $ingredient);
                $unit = is_string($ingredient->unit) ? trim($ingredient->unit) : null;
                $unit = $unit === '' ? null : $unit;
                $name = trim((string) $ingredient->name);
                $unitInfo = $this->unitInfo($unit);
                $key = $this->key($name, $unitInfo, $quantity);

                if ($key !== null && isset($indexes[$key])) {
                    $index = $indexes[$key];
                    $items[$index]['quantity'] = round(
                        (float) $items[$index]['quantity'] + (float) $quantity * $unitInfo['factor'] / $items[$index]['unit_factor'],
                        3,
                        PHP_ROUND_HALF_UP,
                    );
                    $items[$index]['is_optional'] = $items[$index]['is_optional'] && (bool) $ingredient->is_optional;

                    continue;
                }

                if ($key !== null) {
                    $indexes[$key] = count($items);
                }
                $items[] = [
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'preparation' => $ingredient->preparation,
                    'is_optional' => (bool) $ingredient->is_optional,
                    'unit_factor' => $unitInfo['factor'],
                ];
            }
        }

        return array_map(static function (array $item): array {
            unset($item['unit_factor']);

            return $item;
        }, $items);
    }

    /** @return array{family:string, factor:float} */
    private function unitInfo(?string $unit): array
    {
        $normalized = Str::lower(Str::ascii($unit ?? ''));

        return match (true) {
            in_array($normalized, ['mg', 'milligramme', 'milligrammes'], true) => ['family' => 'mass', 'factor' => 0.001],
            in_array($normalized, ['g', 'gramme', 'grammes'], true) => ['family' => 'mass', 'factor' => 1.0],
            in_array($normalized, ['kg', 'kilogramme', 'kilogrammes'], true) => ['family' => 'mass', 'factor' => 1000.0],
            in_array($normalized, ['ml', 'millilitre', 'millilitres'], true) => ['family' => 'volume', 'factor' => 1.0],
            in_array($normalized, ['cl', 'centilitre', 'centilitres'], true) => ['family' => 'volume', 'factor' => 10.0],
            in_array($normalized, ['dl', 'decilitre', 'decilitres'], true) => ['family' => 'volume', 'factor' => 100.0],
            in_array($normalized, ['l', 'litre', 'litres'], true) => ['family' => 'volume', 'factor' => 1000.0],
            in_array($normalized, ['piece', 'pieces', 'unite', 'unites', 'pcs'], true) => ['family' => 'count', 'factor' => 1.0],
            default => ['family' => 'unit:'.$normalized, 'factor' => 1.0],
        };
    }

    /** @param array{family:string, factor:float} $unitInfo */
    private function key(string $name, array $unitInfo, ?float $quantity): ?string
    {
        // A missing quantity cannot be meaningfully summed, even when its unit matches.
        if ($quantity === null) {
            return null;
        }

        return Str::lower(Str::ascii($name)).'|'.$unitInfo['family'];
    }
}
