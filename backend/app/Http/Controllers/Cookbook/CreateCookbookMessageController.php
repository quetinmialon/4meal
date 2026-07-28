<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\StoreCookbookMessageRequest;
use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreateCookbookMessageController extends Controller
{
    public function __invoke(StoreCookbookMessageRequest $request, Cookbook $cookbook): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('create', [CookbookMessage::class, $cookbook]);

        $message = $cookbook->messages()->create([
            'user_id' => $user->getKey(),
            'content' => $request->string('content')->toString(),
        ]);
        $message->load('user');
        $message->setAttribute(
            'member_role',
            $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role'),
        );

        return ApiResponse::success($request, CookbookMessageResource::make($message)->resolve($request), 201);
    }
}
