<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListLatestCookbookMessagesController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', [CookbookMessage::class, $cookbook]);

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
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get()
            ->reverse()
            ->values();

        return ApiResponse::success(
            $request,
            CookbookMessageResource::collection($messages)->resolve($request),
        );
    }
}
