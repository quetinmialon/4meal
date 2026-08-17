<?php

namespace App\Services\Export;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

final class SupmealExportStreamer
{
    private const FORMAT = 'SUPMEAL';

    private const VERSION = '1.0.0';

    private const CHUNK_SIZE = 100;

    public function stream(User $user, bool $includeCookbooks = true): void
    {
        echo '{"format":"'.self::FORMAT.'","version":"'.self::VERSION.'","exported_at":'.json_encode(Carbon::now('UTC')->toJSON(), $this->jsonFlags()).',"source":{"application":"4meal"},"cookbooks":[';

        if ($includeCookbooks) {
            $this->streamCookbooks($user);
        }
        echo '],"recipes":[';
        $this->streamRecipes($user, $includeCookbooks);
        echo ']}';
    }

    /** @param array<string, mixed> $document */
    private function writeJson(array $document): void
    {
        echo json_encode($document, $this->jsonFlags());
    }

    private function streamCookbooks(User $user): void
    {
        $first = true;
        $this->accessibleCookbooks($user)
            ->with([
                'recipes:id,public_id,cookbook_id',
                'linkedRecipes:id,public_id',
            ])
            ->addSelect('cookbooks.id as cookbook_chunk_id')
            ->chunkById(self::CHUNK_SIZE, function ($cookbooks) use (&$first): void {
                foreach ($cookbooks as $cookbook) {
                    /** @var Cookbook $cookbook */
                    if (! $first) {
                        echo ',';
                    }
                    $first = false;

                    $recipeIds = $cookbook->recipes
                        ->concat($cookbook->linkedRecipes)
                        ->pluck('public_id')
                        ->unique()
                        ->values()
                        ->map(fn (string $id): string => $this->id('recipe', $id))
                        ->all();

                    $this->writeJson([
                        'id' => $this->id('cookbook', (string) $cookbook->public_id),
                        'name' => $cookbook->name,
                        'slug' => $cookbook->slug,
                        'description' => $cookbook->description,
                        'recipe_ids' => $recipeIds,
                    ]);
                }
            }, 'cookbooks.id', 'cookbook_chunk_id');
    }

    private function streamRecipes(User $user, bool $includeCookbooks): void
    {
        $first = true;
        $this->accessibleRecipes($user)
            ->with([
                'ingredients',
                'steps',
                'tags',
                'cookbook' => fn ($query) => $query->whereIn('cookbooks.id', $this->accessibleCookbooks($user)->select('cookbooks.id')),
                'cookbooks' => fn ($query) => $query->whereIn('cookbooks.id', $this->accessibleCookbooks($user)->select('cookbooks.id')),
            ])
            ->addSelect('recipes.id as recipe_chunk_id')
            ->chunkById(self::CHUNK_SIZE, function ($recipes) use (&$first, $includeCookbooks): void {
                foreach ($recipes as $recipe) {
                    /** @var Recipe $recipe */
                    if (! $first) {
                        echo ',';
                    }
                    $first = false;
                    $this->writeJson($this->recipePayload($recipe, $includeCookbooks));
                }
            }, 'recipes.id', 'recipe_chunk_id');
    }

    private function accessibleCookbooks(User $user): BelongsToMany
    {
        return $user->cookbooks()->select('cookbooks.*');
    }

    private function accessibleRecipes(User $user): Builder
    {
        $cookbooks = $this->accessibleCookbooks($user)->select('cookbooks.id');

        return Recipe::query()
            ->select('recipes.*')
            ->where(function (Builder $query) use ($user, $cookbooks): void {
                $query->where('recipes.user_id', $user->getKey())
                    ->orWhereIn('recipes.cookbook_id', $cookbooks)
                    ->orWhereHas('cookbooks', fn (Builder $linked): Builder => $linked->whereIn('cookbooks.id', $cookbooks));
            })
            ->orderBy('recipes.id');
    }

    /** @return array<string, mixed> */
    private function recipePayload(Recipe $recipe, bool $includeCookbooks): array
    {
        /** @var Collection<int, RecipeIngredient> $ingredients */
        $ingredients = $recipe->ingredients;
        /** @var Collection<int, RecipeStep> $steps */
        $steps = $recipe->steps;
        $cookbookIds = collect();

        if ($includeCookbooks && $recipe->cookbook !== null) {
            /** @var Cookbook $cookbook */
            $cookbook = $recipe->cookbook;
            $cookbookIds->push($cookbook->public_id);
        }

        if ($includeCookbooks) {
            $cookbookIds = $cookbookIds->concat($recipe->cookbooks->pluck('public_id'))
                ->unique()
                ->values()
                ->map(fn (string $id): string => $this->id('cookbook', $id))
                ->all();
        } else {
            $cookbookIds = [];
        }

        return [
            'id' => $this->id('recipe', (string) $recipe->public_id),
            'title' => $recipe->title,
            'slug' => $recipe->slug,
            'description' => $recipe->description,
            'servings' => $recipe->servings,
            'prep_time_minutes' => $recipe->prep_time_minutes,
            'cook_time_minutes' => $recipe->cook_time_minutes,
            'rest_time_minutes' => $recipe->rest_time_minutes,
            'ingredients' => $ingredients->map(fn (RecipeIngredient $ingredient): array => [
                'name' => $ingredient->name,
                'quantity' => $ingredient->quantity === null ? null : (float) $ingredient->quantity,
                'unit' => $ingredient->unit,
                'preparation' => $ingredient->preparation,
                'optional' => (bool) $ingredient->is_optional,
                'group' => $ingredient->group_name,
            ])->values()->all(),
            'steps' => $steps->map(fn (RecipeStep $step): array => [
                'position' => $step->position,
                'instruction' => $step->instruction,
                'duration_minutes' => $step->duration_minutes,
                'image_url' => null,
            ])->values()->all(),
            'tags' => $recipe->tags->pluck('name')->values()->all(),
            'cookbook_ids' => $cookbookIds,
            'notes' => $recipe->notes,
            'source' => $recipe->source,
            'image_url' => null,
        ];
    }

    private function id(string $type, string $value): string
    {
        return "supmeal:{$type}:{$value}";
    }

    private function jsonFlags(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    }
}
