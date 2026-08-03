<?php

namespace App\Services\Recipe;

use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\User;

final class RecipeAuditRecorder
{
    /** @var list<string> */
    private const SNAPSHOT_FIELDS = [
        'title', 'description', 'prep_time_minutes', 'cook_time_minutes',
        'rest_time_minutes', 'servings', 'visibility', 'difficulty', 'notes', 'source',
    ];

    /** @return array<string, mixed> */
    public function snapshot(Recipe $recipe, bool $includeRelations = true): array
    {
        $snapshot = $recipe->only(self::SNAPSHOT_FIELDS);

        if ($includeRelations) {
            $snapshot['ingredients_count'] = $recipe->relationLoaded('ingredients')
                ? $recipe->ingredients->count() : $recipe->ingredients()->count();
            $snapshot['steps_count'] = $recipe->relationLoaded('steps')
                ? $recipe->steps->count() : $recipe->steps()->count();
            $snapshot['tags_count'] = $recipe->relationLoaded('tags')
                ? $recipe->tags->count() : $recipe->tags()->count();
        }

        return $snapshot;
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    public function record(Recipe $recipe, ?User $actor, string $type, ?array $oldValues, ?array $newValues): RecipeAudit
    {
        /** @var RecipeAudit $audit */
        $audit = $recipe->audits()->create([
            'actor_id' => $actor?->getKey(),
            'type' => $type,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);

        return $audit;
    }
}
