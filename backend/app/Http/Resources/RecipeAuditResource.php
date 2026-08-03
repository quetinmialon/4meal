<?php

namespace App\Http\Resources;

use App\Models\RecipeAudit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeAudit */
class RecipeAuditResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RecipeAudit $audit */
        $audit = $this->resource;

        /** @var User|null $actor */
        $actor = $audit->actor;
        /** @var CarbonInterface|null $createdAt */
        $createdAt = $audit->created_at;

        return [
            'id' => $audit->id,
            'type' => $audit->type,
            'author' => $actor === null ? null : [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'created_at' => $createdAt?->toJSON(),
        ];
    }
}
