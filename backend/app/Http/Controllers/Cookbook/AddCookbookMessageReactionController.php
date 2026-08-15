<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\StoreCookbookMessageReactionRequest;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AddCookbookMessageReactionController extends Controller
{
    public function __invoke(StoreCookbookMessageReactionRequest $request, Cookbook $cookbook, CookbookMessage $message): JsonResponse
    {
        abort_unless((int) $message->cookbook_id === (int) $cookbook->getKey(), 404);
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('react', $message);

        $reaction = $message->reactions()->firstOrCreate([
            'user_id' => $user->getKey(),
            'emoji' => $request->string('emoji')->toString(),
        ]);

        return ApiResponse::success($request, [
            'message_id' => $message->public_id,
            'emoji' => $reaction->emoji,
            'created_at' => $reaction->created_at?->toJSON(),
        ], $reaction->wasRecentlyCreated ? 201 : 200);
    }
}
