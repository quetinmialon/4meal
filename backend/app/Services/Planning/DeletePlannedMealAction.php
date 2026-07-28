<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use Illuminate\Support\Facades\DB;

class DeletePlannedMealAction
{
    public function execute(PlannedMeal $meal): void
    {
        DB::transaction(static function () use ($meal): void {
            $meal->delete();
        });
    }
}
