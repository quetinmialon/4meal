<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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

        $pivot = $cookbook->getAttribute('pivot');
        $memberRole = $cookbook->getAttribute('member_role');

        if (! is_string($memberRole) && $pivot instanceof Pivot) {
            $memberRole = $pivot->getAttribute('role');
        }

        return [
            'id' => $cookbook->public_id,
            'name' => $cookbook->name,
            'slug' => $cookbook->slug,
            'description' => $cookbook->description,
            'image_path' => $cookbook->image_path,
            'image_url' => is_string($cookbook->image_path)
                ? Storage::disk('public')->url($cookbook->image_path)
                : null,
            'owner' => UserResource::make($cookbook->owner)->resolve($request),
            'member_role' => $memberRole,
            'created_at' => $cookbook->created_at?->toJSON(),
        ];
    }
}
