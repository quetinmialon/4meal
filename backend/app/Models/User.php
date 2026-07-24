<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password', // API/application alias for password_hash.
        'password_hash',
        'avatar_path',
        'last_login_at',
        'remember_token_hash',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
        'remember_token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function ownedCookbooks(): HasMany
    {
        return $this->hasMany(Cookbook::class, 'owner_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function authoredRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'author_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function cookbooks(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function favoriteRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_favorites')->withTimestamps();
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => is_string($value) ? mb_strtolower($value) : $value,
        );
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => $attributes['password_hash'] ?? null,
            set: fn (?string $value): array => [
                'password_hash' => is_string($value) && Hash::needsRehash($value)
                    ? Hash::make($value)
                    : $value,
            ],
        );
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token_hash';
    }
}
