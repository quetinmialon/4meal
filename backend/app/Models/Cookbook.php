<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Cookbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'owner_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cookbook $cookbook): void {
            $cookbook->public_id ??= (string) Str::uuid();

            if ($cookbook->slug === null || $cookbook->slug === '') {
                $baseSlug = Str::slug($cookbook->name);
                $slug = $baseSlug;
                $suffix = 2;

                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$suffix;
                    $suffix++;
                }

                $cookbook->slug = $slug;
            }
        });

        static::deleting(function (Cookbook $cookbook): void {
            if (is_string($cookbook->image_path)) {
                Storage::disk('public')->delete($cookbook->image_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cookbook_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
