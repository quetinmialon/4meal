<?php

namespace App\Services\Planning;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreatePlannedMealAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, Recipe $recipe, ?Cookbook $cookbook, array $attributes): PlannedMeal
    {
        return DB::transaction(function () use ($user, $recipe, $cookbook, $attributes): PlannedMeal {
            $recurrence = $attributes['recurrence'] ?? null;
            $recurrenceId = is_array($recurrence) ? (string) Str::uuid() : null;
            $dates = [$attributes['date']];
            if (is_array($recurrence)) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $attributes['date']);
                $until = CarbonImmutable::createFromFormat('!Y-m-d', $recurrence['until']);
                for ($next = $date->addWeek(); $next->lte($until); $next = $next->addWeek()) {
                    $dates[] = $next->format('Y-m-d');
                }
            }

            $query = PlannedMeal::query()->whereIn('date', $dates)->where('meal_type', $attributes['meal_type']);
            $cookbook !== null ? $query->where('cookbook_id', $cookbook->getKey()) : $query->where('user_id', $user->getKey());
            if ($query->exists()) {
                throw new ConflictHttpException('Un repas existe déjà à une des dates demandées pour ce créneau.');
            }

            foreach ($dates as $date) {
                $meal = new PlannedMeal([
                    'recipe_id' => $recipe->getKey(), 'date' => $date, 'meal_type' => $attributes['meal_type'],
                    'note' => $attributes['note'] ?? null, 'initial_servings' => $recipe->servings ?? 1,
                    'servings' => $attributes['servings'] ?? ($user->default_servings ?? 1),
                    'recurrence_id' => $recurrenceId,
                    'recurrence_frequency' => $recurrence['frequency'] ?? null,
                    'recurrence_until' => $recurrence['until'] ?? null,
                ]);
                $cookbook !== null ? $meal->cookbook()->associate($cookbook) : $meal->user()->associate($user);
                $meal->save();
            }

            return PlannedMeal::query()
                ->when($recurrenceId !== null, fn ($query) => $query->where('recurrence_id', $recurrenceId))
                ->when($recurrenceId === null, fn ($query) => $query->whereKey($meal->getKey()))
                ->with(['recipe.ingredients', 'cookbook'])->firstOrFail();
        });
    }
}
