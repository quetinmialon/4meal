<?php

namespace App\Enums;

enum NotificationType: string
{
    case RecipeComment = 'recipe_comment';
    case RecipeCommentReply = 'recipe_comment_reply';
    case CookbookMessage = 'cookbook_message';

    /** @return list<self> */
    public static function current(): array
    {
        return [self::RecipeComment, self::RecipeCommentReply, self::CookbookMessage];
    }
}
