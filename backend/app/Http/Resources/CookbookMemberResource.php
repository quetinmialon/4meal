<?php

namespace App\Http\Resources;

use App\Models\CookbookMember;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CookbookMember
 */
class CookbookMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CookbookMember $member */
        $member = $this->resource;
        $joinedAt = $member->getAttribute('joined_at');

        return [
            'user' => UserResource::make($member->user)->resolve($request),
            'role' => $member->role,
            'joined_at' => $joinedAt instanceof CarbonInterface ? $joinedAt->toJSON() : null,
            'status' => 'active',
        ];
    }
}
