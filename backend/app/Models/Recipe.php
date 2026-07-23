<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cookbook_id',
        'title',
        'name', // API/application alias for title.
        'slug',
        'description',
        'prep_time_minutes',
        'cook_time_minutes',
        'rest_time_minutes',
        'servings',
        'image_path',
        'visibility',
        'difficulty',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Recipe $recipe): void {
            $recipe->public_id ??= (string) Str::uuid();
            $recipe->slug ??= Str::slug($recipe->title);
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => $attributes['title'] ?? null,
            set: fn (?string $value): array => ['title' => $value],
        );
    }

    protected function casts(): array
    {
        return [
            'prep_time_minutes' => 'integer',
            'cook_time_minutes' => 'integer',
            'rest_time_minutes' => 'integer',
            'servings' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }
}
