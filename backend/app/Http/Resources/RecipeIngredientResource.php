<?php

namespace App\Http\Resources;

use App\Models\RecipeIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeIngredient
 */
class RecipeIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'preparation' => $this->preparation,
            'is_optional' => $this->is_optional,
            'group_name' => $this->group_name,
        ];
    }
}
