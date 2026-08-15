<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use Illuminate\Support\Facades\DB;

class DeletePlannedMealAction
{
    public function execute(PlannedMeal $meal, bool $series = false): void
    {
        DB::transaction(static function () use ($meal, $series): void {
            $series && $meal->recurrence_id !== null
                ? PlannedMeal::query()->where('recurrence_id', $meal->recurrence_id)->delete()
                : $meal->delete();
        });
    }
}
