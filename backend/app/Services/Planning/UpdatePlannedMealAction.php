<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use Illuminate\Support\Facades\DB;

class UpdatePlannedMealAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(PlannedMeal $meal, array $attributes): PlannedMeal
    {
        return DB::transaction(function () use ($meal, $attributes): PlannedMeal {
            $meal->fill(array_intersect_key($attributes, array_flip(['date', 'meal_type', 'note'])));
            $meal->save();

            return $meal->load(['recipe', 'cookbook']);
        });
    }
}
