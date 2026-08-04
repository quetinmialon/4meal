<?php

namespace App\Events;

use App\Models\RecipeComment;

final class RecipeCommentCreated
{
    public function __construct(public readonly RecipeComment $comment) {}
}
