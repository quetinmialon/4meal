<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListCookbooksController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $cookbooks = $user->cookbooks()
            ->with(['owner', 'members'])
            ->orderByDesc('cookbooks.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return CookbookResource::collection($cookbooks);
    }
}
