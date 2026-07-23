<?php

namespace App\Http\Controllers\Cookbook;

use App\Models\Cookbook;
use App\Models\User;
use App\Services\Cookbook\RemoveCookbookMemberAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RemoveCookbookMemberController
{
    public function __invoke(
        Request $request,
        Cookbook $cookbook,
        User $member,
        RemoveCookbookMemberAction $action,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        Gate::forUser($actor)->authorize('remove_members', $cookbook);
        $action->execute($cookbook, $actor, $member);

        return response()->noContent();
    }
}
