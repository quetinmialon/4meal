<?php

namespace App\Http\Resources;

use App\Models\RecipeComment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin RecipeComment */
class RecipeCommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RecipeComment $comment */
        $comment = $this->resource;
        /** @var User $author */
        $author = $comment->user;
        $editedAt = $comment->getAttribute('edited_at');
        $reactions = $comment->relationLoaded('reactions') ? $comment->reactions : collect();
        $viewerId = $request->user()?->getAuthIdentifier();

        return [
            'id' => $comment->public_id,
            'parent_id' => $comment->parent instanceof RecipeComment ? $comment->parent->public_id : null,
            'content' => $comment->content,
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'avatar_url' => is_string($author->avatar_path)
                    ? Storage::disk('public')->url($author->avatar_path)
                    : null,
                'role' => $comment->getAttribute('member_role'),
            ],
            'edited_at' => $editedAt instanceof CarbonInterface ? $editedAt->toJSON() : null,
            'created_at' => $comment->created_at?->toJSON(),
            'reactions' => $reactions->groupBy('emoji')->map(fn ($items, $emoji): array => [
                'emoji' => $emoji,
                'count' => $items->count(),
                'reacted' => $viewerId !== null && $items->contains('user_id', (int) $viewerId),
            ])->values()->all(),
        ];
    }
}
