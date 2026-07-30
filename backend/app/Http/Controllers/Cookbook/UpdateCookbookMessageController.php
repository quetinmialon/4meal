<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\UpdateCookbookMessageRequest;
use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpdateCookbookMessageController extends Controller
{
    public function __invoke(UpdateCookbookMessageRequest $request, Cookbook $cookbook, CookbookMessage $message): JsonResponse
    {
        abort_unless((int) $message->cookbook_id === (int) $cookbook->getKey(), 404);
        /** @var User $user */ $user = $request->user();
        Gate::forUser($user)->authorize('update', $message);
        $message->update(['content' => $request->string('content')->toString(), 'edited_at' => now()]);
        $message->load('user', 'deletedBy');
        $message->setAttribute('member_role', $cookbook->members()->whereKey($message->user_id)->value('cookbook_members.role'));

        return ApiResponse::success($request, CookbookMessageResource::make($message)->resolve($request));
    }
}
