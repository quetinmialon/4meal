<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateRecipeAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Recipe $recipe, array $attributes): Recipe
    {
        $newImagePath = null;
        $oldImagePath = $recipe->image_path;

        try {
            if (($attributes['image'] ?? null) instanceof UploadedFile) {
                $newImagePath = Storage::disk('public')->putFile('recipes', $attributes['image']);

                if (! is_string($newImagePath)) {
                    throw new RuntimeException('Recipe image could not be stored.');
                }
            }

            $updatedRecipe = DB::transaction(function () use ($actor, $recipe, $attributes, $newImagePath): Recipe {
                $recipe->fill(array_intersect_key($attributes, array_flip([
                    'title',
                    'description',
                    'prep_time_minutes',
                    'cook_time_minutes',
                    'servings',
                    'source',
                ])));

                if (is_string($newImagePath)) {
                    $recipe->image_path = $newImagePath;
                }

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

            if (is_string($newImagePath) && is_string($oldImagePath) && $oldImagePath !== $newImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return $updatedRecipe;
        } catch (Throwable $exception) {
            if (is_string($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }
    }
}
