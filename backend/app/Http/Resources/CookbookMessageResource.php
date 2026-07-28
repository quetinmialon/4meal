<?php

namespace App\Http\Resources;

use App\Models\CookbookMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin CookbookMessage */
class CookbookMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CookbookMessage $message */
        $message = $this->resource;
        /** @var User $author */
        $author = $message->user;

        return [
            'id' => $message->public_id,
            'content' => $message->content,
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'avatar_url' => is_string($author->avatar_path)
                    ? Storage::disk('public')->url($author->avatar_path)
                    : null,
                'role' => $message->getAttribute('member_role'),
            ],
            'created_at' => $message->created_at?->toJSON(),
        ];
    }
}
