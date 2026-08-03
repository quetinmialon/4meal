<?php

namespace App\Services\Recipe;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateRecipeAction
{
    public function execute(User $actor, Recipe $source, ?Cookbook $cookbook = null): Recipe
    {
        return DB::transaction(function () use ($actor, $source, $cookbook): Recipe {
            $source->loadMissing(['ingredients', 'steps', 'tags']);

            $copy = new Recipe([
                'author_id' => $actor->getKey(),
                'title' => $source->title,
                'description' => $source->description,
                'prep_time_minutes' => $source->prep_time_minutes,
                'cook_time_minutes' => $source->cook_time_minutes,
                'rest_time_minutes' => $source->rest_time_minutes,
                'servings' => $source->servings,
                'visibility' => $source->visibility,
                'difficulty' => $source->difficulty,
                'notes' => $source->notes,
                'source' => $source->source,
                // Image paths are intentionally not copied; see REC-14 decision.
                'image_path' => null,
            ]);

            $cookbook !== null
                ? $copy->cookbook()->associate($cookbook)
                : $copy->user()->associate($actor);

            $copy->save();

            foreach ($source->ingredients as $ingredient) {
                $copy->ingredients()->create($ingredient->only([
                    'position', 'name', 'quantity', 'unit', 'preparation', 'is_optional', 'group_name',
                ]));
            }

            foreach ($source->steps as $step) {
                $copy->steps()->create($step->only([
                    'position', 'instruction', 'duration_minutes',
                ]));
            }

            $tagIds = [];
            /** @var Tag $tag */
            foreach ($source->tags as $tag) {
                $tagIds[] = $actor->tags()->firstOrCreate(
                    ['slug' => Str::slug($tag->name)],
                    ['name' => $tag->name, 'color' => $tag->color],
                )->getKey();
            }
            $copy->tags()->sync($tagIds);

            app(RecipeAuditRecorder::class)->record(
                $copy,
                $actor,
                RecipeAudit::CREATED,
                null,
                app(RecipeAuditRecorder::class)->snapshot($copy),
            );

            return $copy->load(['user', 'author', 'cookbook', 'ingredients', 'steps', 'tags']);
        });
    }
}
