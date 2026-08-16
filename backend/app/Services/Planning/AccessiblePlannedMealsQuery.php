<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessiblePlannedMealsQuery
{
    public function for(User $user, string $from, string $to, ?string $cookbookPublicId = null, array $cookbookPublicIds = [], ?bool $includePersonal = null): Builder
    {
        $accessibleCookbookIds = $user->cookbooks()->select('cookbooks.id');
        $selectedCookbookIds = $user->cookbooks()
            ->when($cookbookPublicIds !== [], fn (Builder $query): Builder => $query->whereIn('cookbooks.public_id', $cookbookPublicIds))
            ->select('cookbooks.id');
        $hasSelectedCookbooks = $cookbookPublicIds !== [];

        return PlannedMeal::query()
            ->select([
                'planned_meals.id',
                'planned_meals.public_id',
                'planned_meals.user_id',
                'planned_meals.cookbook_id',
                'planned_meals.recipe_id',
                'planned_meals.date',
                'planned_meals.meal_type',
                'planned_meals.note',
                'planned_meals.initial_servings',
                'planned_meals.servings',
                'planned_meals.recurrence_id',
                'planned_meals.recurrence_frequency',
                'planned_meals.recurrence_until',
                'planned_meals.created_at',
            ])
            ->with([
                'recipe:id,public_id,title,slug,image_path,servings',
                'recipe.ingredients',
                'cookbook:id,public_id',
            ])
            ->whereDate('planned_meals.date', '>=', $from)
            ->whereDate('planned_meals.date', '<=', $to)
            ->where(function (Builder $query) use ($user, $cookbookPublicId, $accessibleCookbookIds, $selectedCookbookIds, $includePersonal, $hasSelectedCookbooks): void {
                if ($cookbookPublicId !== null) {
                    $query->whereHas('cookbook', function (Builder $query) use ($cookbookPublicId): void {
                        $query->where('public_id', $cookbookPublicId);
                    });
                    $query->whereIn('planned_meals.cookbook_id', $accessibleCookbookIds);

                    return;
                }

                if ($includePersonal === null && ! $hasSelectedCookbooks) {
                    $query->where('planned_meals.user_id', $user->getKey())
                        ->orWhereIn('planned_meals.cookbook_id', $accessibleCookbookIds);

                    return;
                }

                if ($includePersonal === true) {
                    $query->where('planned_meals.user_id', $user->getKey());
                    if ($hasSelectedCookbooks) {
                        $query->orWhereIn('planned_meals.cookbook_id', $selectedCookbookIds);
                    }
                } elseif ($hasSelectedCookbooks) {
                    $query->whereIn('planned_meals.cookbook_id', $selectedCookbookIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('planned_meals.date')
            ->orderBy('planned_meals.meal_type')
            ->orderBy('planned_meals.id');
    }
}
