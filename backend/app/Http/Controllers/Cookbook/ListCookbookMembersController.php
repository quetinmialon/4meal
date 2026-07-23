<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookMemberResource;
use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ListCookbookMembersController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $cookbook);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $members = CookbookMember::query()
            ->where('cookbook_id', $cookbook->getKey())
            ->with('user')
            ->orderBy('cookbook_members.joined_at')
            ->orderBy('cookbook_members.id')
            ->paginate($perPage)
            ->withQueryString();

        return CookbookMemberResource::collection($members);
    }
}
