<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cookbook
 */
class CookbookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cookbook $cookbook */
        $cookbook = $this->resource;

        return [
            'id' => $cookbook->public_id,
            'name' => $cookbook->name,
            'owner' => UserResource::make($cookbook->owner)->resolve($request),
            'created_at' => $cookbook->created_at?->toJSON(),
        ];
    }
}
