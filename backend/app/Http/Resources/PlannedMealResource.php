<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Planning\PlannedMealIngredientCalculator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin PlannedMeal */
class PlannedMealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PlannedMeal $meal */
        $meal = $this->resource;
        $date = $meal->getAttribute('date');
        $recipe = $meal->getRelation('recipe');
        $cookbook = $meal->getRelation('cookbook');

        return [
            'id' => $meal->public_id,
            'date' => $date instanceof DateTimeInterface
                ? $date->format('Y-m-d')
                : (is_string($date) ? substr($date, 0, 10) : null),
            'meal_type' => $meal->meal_type,
            'note' => $meal->note,
            'initial_servings' => $meal->initial_servings,
            'servings' => $meal->servings,
            'recipe' => $this->whenLoaded('recipe', fn (): array => $recipe instanceof Recipe ? [
                'id' => $recipe->public_id,
                'title' => $recipe->title,
                'slug' => $recipe->slug,
                'servings' => $recipe->servings,
                ...($recipe->relationLoaded('ingredients') && $recipe->ingredients->isNotEmpty() ? ['ingredients' => (function () use ($recipe, $meal): array {
                    /** @var Collection<int, RecipeIngredient> $ingredients */
                    $ingredients = $recipe->ingredients;

                    return $ingredients->map(fn (RecipeIngredient $ingredient): array => [
                        'position' => $ingredient->position,
                        'name' => $ingredient->name,
                        'quantity' => app(PlannedMealIngredientCalculator::class)->quantity($meal, $ingredient),
                        'unit' => $ingredient->unit,
                        'preparation' => $ingredient->preparation,
                        'is_optional' => $ingredient->is_optional,
                        'group_name' => $ingredient->group_name,
                    ])->values()->all();
                })(),
                ] : []),
                'image_url' => is_string($recipe->image_path) && Storage::disk('public')->exists($recipe->image_path)
                    ? Storage::disk('public')->url($recipe->image_path)
                    : null,
            ] : []),
            'cookbook_id' => $cookbook instanceof Cookbook ? $cookbook->public_id : null,
            'created_at' => $meal->created_at?->toJSON(),
        ];
    }
}
