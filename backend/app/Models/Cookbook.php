<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Cookbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cookbook $cookbook): void {
            $cookbook->public_id ??= (string) Str::uuid();
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
            ->withPivot('role')
            ->withTimestamps();
    }
}
