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

        return [
            'id' => $comment->public_id,
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
        ];
    }
}
