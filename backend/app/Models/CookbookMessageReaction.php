<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookbookMessageReaction extends Model
{
    use HasFactory;

    /** @var list<string> */
    public const ALLOWED_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡'];

    protected $fillable = [
        'cookbook_message_id',
        'user_id',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(CookbookMessage::class, 'cookbook_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
