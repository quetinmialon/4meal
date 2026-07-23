<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Requests\Cookbook\UpdateCookbookMemberRoleRequest;
use App\Http\Resources\CookbookMemberResource;
use App\Models\Cookbook;
use App\Models\User;
use App\Services\Cookbook\ChangeCookbookMemberRoleAction;
use Illuminate\Http\JsonResponse;

class UpdateCookbookMemberRoleController
{
    public function __invoke(
        UpdateCookbookMemberRoleRequest $request,
        Cookbook $cookbook,
        User $member,
        ChangeCookbookMemberRoleAction $action,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $updatedMember = $action->execute($cookbook, $actor, $member, $request->validated('role'));

        return response()->json(CookbookMemberResource::make($updatedMember)->resolve($request));
    }
}
