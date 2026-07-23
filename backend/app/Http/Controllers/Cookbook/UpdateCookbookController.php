<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\UpdateCookbookRequest;
use App\Http\Resources\CookbookResource;
use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use App\Services\Cookbook\UpdateCookbookAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpdateCookbookController extends Controller
{
    public function __invoke(
        UpdateCookbookRequest $request,
        Cookbook $cookbook,
        UpdateCookbookAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $cookbook);

        $cookbook = $action->execute($cookbook, $request->safe()->only([
            'name', 'slug', 'description', 'image',
        ]));
        $cookbook->setAttribute(
            'member_role',
            CookbookMember::query()
                ->where('cookbook_id', $cookbook->getKey())
                ->where('user_id', $user->getKey())
                ->value('role'),
        );

        return ApiResponse::success(
            $request,
            CookbookResource::make($cookbook)->resolve($request),
        );
    }
}
