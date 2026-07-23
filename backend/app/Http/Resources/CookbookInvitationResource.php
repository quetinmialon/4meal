<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CookbookInvitation */
class CookbookInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CarbonInterface|null $expiresAt */
        $expiresAt = $this->getAttribute('expires_at');
        /** @var CarbonInterface|null $acceptedAt */
        $acceptedAt = $this->getAttribute('accepted_at');
        /** @var CarbonInterface|null $declinedAt */
        $declinedAt = $this->getAttribute('declined_at');
        /** @var Cookbook $cookbook */
        $cookbook = $this->cookbook;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'expires_at' => $expiresAt?->toJSON(),
            'accepted_at' => $acceptedAt?->toJSON(),
            'declined_at' => $declinedAt?->toJSON(),
            'cookbook' => [
                'id' => $cookbook->public_id,
                'name' => $cookbook->name,
            ],
        ];
    }
}
