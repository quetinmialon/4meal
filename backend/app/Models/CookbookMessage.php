<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CookbookMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'cookbook_id',
        'user_id',
        'content',
        'edited_at',
        'deleted_at',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (CookbookMessage $message): void {
            $message->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    /** @return HasMany<CookbookMessageReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(CookbookMessageReaction::class);
    }
}
