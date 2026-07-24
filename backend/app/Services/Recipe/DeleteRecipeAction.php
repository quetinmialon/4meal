<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class DeleteRecipeAction
{
    public function execute(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $recipe->tags()->detach();
            $recipe->delete();
        });
    }
}
