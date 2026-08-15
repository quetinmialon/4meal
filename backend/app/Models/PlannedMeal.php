<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PlannedMeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cookbook_id',
        'recipe_id',
        'date',
        'meal_type',
        'note',
        'initial_servings',
        'servings',
        'recurrence_id',
        'recurrence_frequency',
        'recurrence_until',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'initial_servings' => 'integer',
            'servings' => 'integer',
            'recurrence_until' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlannedMeal $meal): void {
            if (($meal->user_id === null) === ($meal->cookbook_id === null)) {
                throw new InvalidArgumentException('A planned meal must belong to exactly one user or cookbook.');
            }

            $meal->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
