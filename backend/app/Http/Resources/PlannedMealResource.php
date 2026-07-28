<?php

namespace App\Http\Resources;

use App\Models\PlannedMeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlannedMeal */
class PlannedMealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PlannedMeal $meal */
        $meal = $this->resource;

        return [
            'id' => $meal->public_id,
            'date' => $meal->date?->format('Y-m-d'),
            'meal_type' => $meal->meal_type,
            'note' => $meal->note,
            'initial_servings' => $meal->initial_servings,
            'recipe' => $this->whenLoaded('recipe', fn (): array => RecipeResource::make($meal->recipe)->resolve($request)),
            'cookbook_id' => $meal->cookbook?->public_id,
            'created_at' => $meal->created_at?->toJSON(),
        ];
    }
}
