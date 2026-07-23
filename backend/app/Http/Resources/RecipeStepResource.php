<?php

namespace App\Http\Resources;

use App\Models\RecipeStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecipeStep
 */
class RecipeStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'instruction' => $this->instruction,
            'duration_minutes' => $this->duration_minutes,
        ];
    }
}
