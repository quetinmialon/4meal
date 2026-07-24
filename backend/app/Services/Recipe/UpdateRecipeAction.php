<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateRecipeAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Recipe $recipe, array $attributes): Recipe
    {
        return DB::transaction(function () use ($actor, $recipe, $attributes): Recipe {
            $recipe->fill(array_intersect_key($attributes, array_flip([
                'title',
                'description',
                'prep_time_minutes',
                'cook_time_minutes',
                'servings',
                'source',
            ])));
            $recipe->save();

            if (array_key_exists('ingredients', $attributes)) {
                $recipe->ingredients()->delete();

                foreach ($attributes['ingredients'] as $index => $ingredient) {
                    $recipe->ingredients()->create([
                        ...$ingredient,
                        'position' => $ingredient['position'] ?? $index + 1,
                    ]);
                }
            }

            if (array_key_exists('steps', $attributes)) {
                $recipe->steps()->delete();

                foreach ($attributes['steps'] as $index => $step) {
                    $recipe->steps()->create([
                        ...$step,
                        'position' => $step['position'] ?? $index + 1,
                    ]);
                }
            }

            if (array_key_exists('tags', $attributes)) {
                $tagIds = collect($attributes['tags'])
                    ->map(fn (string $name): int => $actor->tags()->firstOrCreate(
                        ['slug' => Str::slug($name)],
                        ['name' => $name],
                    )->getKey())
                    ->all();

                $recipe->tags()->sync($tagIds);
            }

            return $recipe->load(['user', 'author', 'cookbook', 'ingredients', 'steps', 'tags']);
        });
    }
}
