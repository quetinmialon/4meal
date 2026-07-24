<?php

namespace App\Services\Recipe;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRecipeAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, ?Cookbook $cookbook, array $attributes): Recipe
    {
        return DB::transaction(function () use ($user, $cookbook, $attributes): Recipe {
            $recipe = new Recipe([
                'author_id' => $user->getKey(),
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'prep_time_minutes' => $attributes['prep_time_minutes'] ?? null,
                'cook_time_minutes' => $attributes['cook_time_minutes'] ?? null,
                'servings' => $attributes['servings'] ?? null,
                'source' => $attributes['source'] ?? null,
            ]);
            $cookbook !== null
                ? $recipe->cookbook()->associate($cookbook)
                : $recipe->user()->associate($user);
            $recipe->save();

            foreach ($attributes['ingredients'] as $index => $ingredient) {
                $recipe->ingredients()->create([
                    ...$ingredient,
                    'position' => $ingredient['position'] ?? $index + 1,
                ]);
            }

            foreach ($attributes['steps'] as $index => $step) {
                $recipe->steps()->create([
                    ...$step,
                    'position' => $step['position'] ?? $index + 1,
                ]);
            }

            $tagIds = collect($attributes['tags'] ?? [])
                ->map(fn (string $name): int => $user->tags()->firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name],
                )->getKey())
                ->all();
            $recipe->tags()->sync($tagIds);

            return $recipe->load(['user', 'cookbook', 'ingredients', 'steps', 'tags']);
        });
    }
}
