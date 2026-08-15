<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Recipe
 */
class RecipeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Recipe $recipe */
        $recipe = $this->resource;

        return [
            'id' => $recipe->public_id,
            'name' => $recipe->getAttribute('title'),
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'description' => $recipe->description,
            'prep_time_minutes' => $recipe->prep_time_minutes,
            'cook_time_minutes' => $recipe->cook_time_minutes,
            'rest_time_minutes' => $recipe->rest_time_minutes,
            'servings' => $recipe->servings,
            'image_path' => $recipe->image_path,
            'image_url' => is_string($recipe->image_path) && Storage::disk('public')->exists($recipe->image_path)
                ? Storage::disk('public')->url($recipe->image_path)
                : null,
            'visibility' => $recipe->visibility,
            'difficulty' => $recipe->difficulty,
            'notes' => $recipe->notes,
            'source' => $recipe->source,
            'is_favorite' => (bool) ($recipe->getAttribute('is_favorite') ?? false),
            'personal_rating' => $recipe->getAttribute('personal_rating') === null
                ? null
                : (int) $recipe->getAttribute('personal_rating'),
            'average_rating' => (float) ($recipe->getAttribute('average_rating') ?? 0),
            'rating_count' => (int) ($recipe->getAttribute('rating_count') ?? $recipe->getAttribute('ratings_count') ?? 0),
            'author' => $this->whenLoaded('author', fn (): ?array => $recipe->author === null
                ? null
                : UserResource::make($recipe->author)->resolve($request)),
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('ingredients')),
            'steps' => RecipeStepResource::collection($this->whenLoaded('steps')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $recipe->created_at?->toJSON(),
        ];
    }
}
