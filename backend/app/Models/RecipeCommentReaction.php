<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeCommentReaction extends Model
{
    use HasFactory;

    /** @var list<string> */
    public const ALLOWED_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    protected $fillable = ['recipe_comment_id', 'user_id', 'emoji'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(RecipeComment::class, 'recipe_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
