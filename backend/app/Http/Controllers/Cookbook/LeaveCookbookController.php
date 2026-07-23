<?php

namespace App\Http\Controllers\Cookbook;

use App\Models\Cookbook;
use App\Models\User;
use App\Services\Cookbook\LeaveCookbookAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LeaveCookbookController
{
    public function __invoke(Request $request, Cookbook $cookbook, LeaveCookbookAction $action): Response
    {
        /** @var User $member */
        $member = $request->user();
        Gate::forUser($member)->authorize('leave', $cookbook);
        $action->execute($cookbook, $member);

        return response()->noContent();
    }
}
