<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasFactory;

    protected $fillable = ['recipe_id', 'position', 'name', 'quantity', 'unit', 'preparation', 'is_optional', 'group_name'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'quantity' => 'decimal:3', 'is_optional' => 'boolean'];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
