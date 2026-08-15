<?php

namespace App\Services\Recipe;

use App\Models\CookbookMember;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AccessibleRecipesQuery
{
    /** @param array<string, mixed> $filters */
    public function for(User $user, string $scope = 'accessible', ?string $search = null, array $filters = []): Builder
    {
        $query = Recipe::query()
            ->with(['ingredients', 'steps', 'tags', 'author'])
            ->withExists([
                'favoritedBy as is_favorite' => fn (Builder $query) => $query->whereKey($user->getKey()),
            ])
            ->withAggregate([
                'ratings as personal_rating' => fn (Builder $query) => $query->where('user_id', $user->getKey()),
            ], 'rating');
        $query->withAvg('ratings as average_rating', 'rating')
            ->withCount('ratings');

        if ($scope === 'mine' || $scope === 'public') {
            $query->whereNotNull('user_id');
        }

        if ($scope === 'mine') {
            $query->where('user_id', $user->getKey());
        } elseif ($scope !== 'public') {
            $query->where(function (Builder $query) use ($user): void {
                $query
                    ->whereNotNull('user_id')
                    ->orWhereIn(
                        'cookbook_id',
                        CookbookMember::query()
                            ->where('user_id', $user->getKey())
                            ->select('cookbook_id'),
                    )
                    ->orWhereExists(function ($query) use ($user): void {
                        $query->selectRaw('1')
                            ->from('cookbook_recipe')
                            ->whereColumn('cookbook_recipe.recipe_id', 'recipes.id')
                            ->whereIn('cookbook_recipe.cookbook_id', CookbookMember::query()
                                ->where('user_id', $user->getKey())
                                ->select('cookbook_id'));
                    });
            });
        }

        $search = is_string($search) ? trim($search) : '';

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $this->applyFilters($query, $user, $filters);

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, User $user, array $filters): void
    {
        if (isset($filters['cookbook_id'])) {
            $cookbookId = $filters['cookbook_id'];

            $query->where(function (Builder $query) use ($cookbookId): void {
                $query->whereHas('cookbook', fn (Builder $cookbook): Builder => $cookbook->where('public_id', $cookbookId))
                    ->orWhereHas('cookbooks', fn (Builder $cookbooks): Builder => $cookbooks->where('public_id', $cookbookId));
            });
        }

        if (isset($filters['tag'])) {
            $tag = mb_strtolower((string) $filters['tag']);

            $query->whereHas('tags', fn (Builder $tags): Builder => $tags
                ->whereRaw('LOWER(tags.name) = ?', [$tag])
                ->orWhereRaw('LOWER(tags.slug) = ?', [$tag]));
        }

        if (isset($filters['ingredient'])) {
            $ingredient = mb_strtolower((string) $filters['ingredient']);

            $query->whereHas('ingredients', fn (Builder $ingredients): Builder => $ingredients
                ->whereRaw('LOWER(recipe_ingredients.name) = ?', [$ingredient]));
        }

        if (isset($filters['max_prep_time'])) {
            $query->where('prep_time_minutes', '<=', $filters['max_prep_time']);
        }

        if (isset($filters['max_cook_time'])) {
            $query->where('cook_time_minutes', '<=', $filters['max_cook_time']);
        }

        if (filter_var($filters['favorites'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('favoritedBy', fn (Builder $favorites): Builder => $favorites->whereKey($user->getKey()));
        }

        if (isset($filters['min_rating'])) {
            $query->whereRaw(<<<'SQL'
                COALESCE((SELECT AVG(rating) FROM recipe_ratings WHERE recipe_ratings.recipe_id = recipes.id), 0) >= ?
            SQL, [$filters['min_rating']]);
        }

        if (($filters['sort'] ?? null) === 'rating_desc') {
            $query->orderByRaw(<<<'SQL'
                (SELECT COALESCE(AVG(rating), 0) FROM recipe_ratings WHERE recipe_ratings.recipe_id = recipes.id) DESC
            SQL);
        } elseif (($filters['sort'] ?? null) === 'rating_asc') {
            $query->orderByRaw(<<<'SQL'
                (SELECT COALESCE(AVG(rating), 0) FROM recipe_ratings WHERE recipe_ratings.recipe_id = recipes.id) ASC
            SQL);
        }
    }

    private function applySearch(Builder $query, string $search): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $tsQuery = "websearch_to_tsquery('french', ? )";
            $similarityThreshold = 0.32;
            $fuzzyBindings = [$search, $search, $search, $search, $search];

            $query
                ->where(function (Builder $query) use ($search, $tsQuery, $similarityThreshold): void {
                    $query
                        ->whereRaw("recipes.search_vector @@ {$tsQuery}", [$search])
                        ->orWhereRaw('word_similarity(LOWER(?), LOWER(recipes.title)) >= ?', [$search, $similarityThreshold])
                        ->orWhereHas('ingredients', fn (Builder $ingredients): Builder => $ingredients
                            ->whereRaw('word_similarity(LOWER(?), LOWER(recipe_ingredients.name)) >= ?', [$search, $similarityThreshold]))
                        ->orWhereHas('steps', fn (Builder $steps): Builder => $steps
                            ->whereRaw('word_similarity(LOWER(?), LOWER(recipe_steps.instruction)) >= ?', [$search, $similarityThreshold]))
                        ->orWhereHas('tags', fn (Builder $tags): Builder => $tags
                            ->where(function (Builder $tags) use ($search, $similarityThreshold): void {
                                $tags
                                    ->whereRaw('word_similarity(LOWER(?), LOWER(tags.name)) >= ?', [$search, $similarityThreshold])
                                    ->orWhereRaw('word_similarity(LOWER(?), LOWER(tags.slug)) >= ?', [$search, $similarityThreshold]);
                            }));
                })
                ->orderByRaw("ts_rank(recipes.search_vector, {$tsQuery}) DESC", [$search])
                ->orderByRaw(<<<'SQL'
GREATEST(
    word_similarity(LOWER(?), LOWER(recipes.title)),
    COALESCE((SELECT MAX(word_similarity(LOWER(?), LOWER(i.name))) FROM recipe_ingredients i WHERE i.recipe_id = recipes.id), 0),
    COALESCE((SELECT MAX(word_similarity(LOWER(?), LOWER(s.instruction))) FROM recipe_steps s WHERE s.recipe_id = recipes.id), 0),
    COALESCE((SELECT MAX(GREATEST(word_similarity(LOWER(?), LOWER(t.name)), word_similarity(LOWER(?), LOWER(t.slug))))
        FROM tags t JOIN recipe_tag rt ON rt.tag_id = t.id WHERE rt.recipe_id = recipes.id), 0)
) DESC
SQL, $fuzzyBindings);

            return;
        }

        // SQLite is used by the fast application test suite; PostgreSQL is the production path.
        $like = '%'.mb_strtolower($search).'%';

        $query
            ->where(function (Builder $query) use ($like): void {
                $query->whereRaw('LOWER(recipes.title) LIKE ?', [$like])
                    ->orWhereHas('ingredients', fn (Builder $ingredients) => $ingredients
                        ->whereRaw('LOWER(recipe_ingredients.name) LIKE ?', [$like]))
                    ->orWhereHas('steps', fn (Builder $steps) => $steps
                        ->whereRaw('LOWER(recipe_steps.instruction) LIKE ?', [$like]))
                    ->orWhereHas('tags', fn (Builder $tags) => $tags
                        ->where(function (Builder $tags) use ($like): void {
                            $tags->whereRaw('LOWER(tags.name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(tags.slug) LIKE ?', [$like]);
                        }));
            })
            ->orderByRaw('CASE WHEN LOWER(recipes.title) LIKE ? THEN 0 ELSE 1 END ASC', [$like]);
    }
}
