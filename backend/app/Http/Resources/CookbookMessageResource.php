<?php

namespace App\Http\Resources;

use App\Models\CookbookMessage;
use App\Models\User;
use Carbon\CarbonInterface;
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
        /** @var User|null $deletedBy */
        $deletedBy = $message->deletedBy;
        $isDeleted = $message->deleted_at !== null;
        $editedAt = $message->getAttribute('edited_at');
        $deletedAt = $message->getAttribute('deleted_at');

        return [
            'id' => $message->public_id,
            'content' => $isDeleted && $deletedBy instanceof User ? 'Message supprimé par '.$deletedBy->name : $message->content,
            'is_deleted' => $isDeleted,
            'edited_at' => $editedAt instanceof CarbonInterface ? $editedAt->toJSON() : null,
            'deleted_at' => $deletedAt instanceof CarbonInterface ? $deletedAt->toJSON() : null,
            'deleted_by' => $deletedBy instanceof User ? ['id' => $deletedBy->id, 'name' => $deletedBy->name] : null,
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
