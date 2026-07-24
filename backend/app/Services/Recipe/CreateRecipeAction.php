<?php

namespace App\Services\Recipe;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateRecipeAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, ?Cookbook $cookbook, array $attributes): Recipe
    {
        $storedImagePath = null;

        try {
            return DB::transaction(function () use ($user, $cookbook, $attributes, &$storedImagePath): Recipe {
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

                if (($attributes['image'] ?? null) instanceof UploadedFile) {
                    $storedImagePath = Storage::disk('public')->putFile('recipes', $attributes['image']);

                    if (! is_string($storedImagePath)) {
                        throw new RuntimeException('Recipe image could not be stored.');
                    }

                    $recipe->image_path = $storedImagePath;
                }

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

                return $recipe->load(['user', 'author', 'cookbook', 'ingredients', 'steps', 'tags']);
            });
        } catch (Throwable $exception) {
            if (is_string($storedImagePath)) {
                Storage::disk('public')->delete($storedImagePath);
            }

            throw $exception;
        }
    }
}
