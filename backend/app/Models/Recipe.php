<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'author_id',
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
        'source',
    ];

    protected static function booted(): void
    {
        static::creating(function (Recipe $recipe): void {
            if (($recipe->user_id === null) === ($recipe->cookbook_id === null)) {
                throw new InvalidArgumentException('A recipe must belong to exactly one user or cookbook.');
            }

            $recipe->public_id ??= (string) Str::uuid();

            if ($recipe->slug === null || $recipe->slug === '') {
                $baseSlug = Str::slug($recipe->title);
                $slug = $baseSlug;
                $suffix = 2;

                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }

                $recipe->slug = $slug;
            }
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

    public function cookbooks(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_recipe')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag')->withTimestamps();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recipe_favorites')->withTimestamps();
    }

    public function plannedMeals(): HasMany
    {
        return $this->hasMany(PlannedMeal::class);
    }
}
