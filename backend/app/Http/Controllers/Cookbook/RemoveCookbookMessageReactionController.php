<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\StoreCookbookMessageReactionRequest;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RemoveCookbookMessageReactionController extends Controller
{
    public function __invoke(StoreCookbookMessageReactionRequest $request, Cookbook $cookbook, CookbookMessage $message): Response
    {
        abort_unless((int) $message->cookbook_id === (int) $cookbook->getKey(), 404);
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('unreact', $message);

        $message->reactions()
            ->where('user_id', $user->getKey())
            ->where('emoji', $request->string('emoji')->toString())
            ->delete();

        return response()->noContent();
    }
}
