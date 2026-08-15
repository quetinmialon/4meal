<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\ListCookbookMessagesRequest;
use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ListCookbookMessagesController extends Controller
{
    public function __invoke(ListCookbookMessagesRequest $request, Cookbook $cookbook): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', [CookbookMessage::class, $cookbook]);

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $messages = CookbookMessage::query()
            ->where('cookbook_id', $cookbook->getKey())
            ->addSelect([
                'member_role' => CookbookMember::query()
                    ->select('role')
                    ->whereColumn('cookbook_members.cookbook_id', 'cookbook_messages.cookbook_id')
                    ->whereColumn('cookbook_members.user_id', 'cookbook_messages.user_id')
                    ->limit(1),
            ])
            ->with('user', 'deletedBy', 'reactions')
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->input('cursor'));

        return ApiResponse::success($request, CookbookMessageResource::collection($messages->items())->resolve($request), 200, [
            'pagination' => [
                'per_page' => $messages->perPage(),
                'next_cursor' => $messages->nextCursor()?->encode(),
                'previous_cursor' => $messages->previousCursor()?->encode(),
            ],
        ]);
    }
}
