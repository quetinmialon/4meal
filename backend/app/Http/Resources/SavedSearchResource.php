<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedSearch */
class SavedSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SavedSearch $savedSearch */
        $savedSearch = $this->resource;

        return [
            'id' => $savedSearch->public_id,
            'name' => $savedSearch->name,
            'criteria' => $savedSearch->criteria,
            'created_at' => $savedSearch->created_at?->toJSON(),
            'updated_at' => $savedSearch->updated_at?->toJSON(),
        ];
    }
}
