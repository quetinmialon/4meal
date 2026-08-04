<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeComment;
use Illuminate\Validation\ValidationException;

class RecipeCommentReplyService
{
    /** Root comments have depth 0; replies may be nested up to depth 3. */
    public const MAX_REPLY_DEPTH = 3;

    public function create(Recipe $recipe, int $userId, string $content, ?string $parentPublicId = null): RecipeComment
    {
        $parentId = null;
        if ($parentPublicId !== null) {
            $parent = RecipeComment::query()->where('public_id', $parentPublicId)->first();
            if (! $parent instanceof RecipeComment) {
                throw ValidationException::withMessages(['parent_id' => 'Le commentaire parent est introuvable.']);
            }
            if ((int) $parent->recipe_id !== (int) $recipe->getKey()) {
                throw ValidationException::withMessages(['parent_id' => 'Le commentaire parent doit appartenir à cette recette.']);
            }
            if ($this->depthOf($parent) >= self::MAX_REPLY_DEPTH) {
                throw ValidationException::withMessages(['parent_id' => 'La profondeur maximale des réponses est de '.self::MAX_REPLY_DEPTH.'.']);
            }
            $parentId = $parent->getKey();
        }

        /** @var RecipeComment $comment */
        $comment = $recipe->comments()->create([
            'user_id' => $userId,
            'content' => $content,
            'parent_id' => $parentId,
        ]);

        return $comment;
    }

    private function depthOf(RecipeComment $comment): int
    {
        $depth = 0;
        $visited = [];
        $current = $comment;
        while ($current->parent_id !== null) {
            $key = (int) $current->getKey();
            if (isset($visited[$key])) {
                throw ValidationException::withMessages(['parent_id' => 'Les références circulaires sont interdites.']);
            }
            $visited[$key] = true;
            $depth++;
            $current = $current->parent()->firstOrFail();
        }

        return $depth;
    }
}
