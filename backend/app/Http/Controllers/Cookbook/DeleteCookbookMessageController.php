<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeleteCookbookMessageController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook, CookbookMessage $message): JsonResponse
    {
        abort_unless((int) $message->cookbook_id === (int) $cookbook->getKey(), 404);
        /** @var User $user */ $user = $request->user();
        Gate::forUser($user)->authorize('delete', $message);
        $message->update(['deleted_at' => now(), 'deleted_by_user_id' => $user->getKey()]);
        $message->load('user', 'deletedBy', 'reactions');
        $message->setAttribute('member_role', $cookbook->members()->whereKey($message->user_id)->value('cookbook_members.role'));

        return ApiResponse::success($request, CookbookMessageResource::make($message)->resolve($request));
    }
}
