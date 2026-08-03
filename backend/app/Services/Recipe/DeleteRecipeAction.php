<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteRecipeAction
{
    public function execute(Recipe $recipe, ?User $actor = null): void
    {
        $audits = app(RecipeAuditRecorder::class);
        DB::transaction(function () use ($recipe, $actor, $audits): void {
            $before = $audits->snapshot($recipe);
            $recipe->ingredients()->delete();
            $recipe->steps()->delete();
            $recipe->tags()->detach();
            $recipe->delete();
            $audits->record($recipe, $actor, RecipeAudit::DELETED, $before, null);
        });
    }
}
