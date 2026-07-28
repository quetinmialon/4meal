<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookMessageResource;
use App\Http\Resources\CookbookResource;
use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowCookbookController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $cookbook);

        $cookbook->setAttribute(
            'member_role',
            CookbookMember::query()
                ->where('cookbook_id', $cookbook->getKey())
                ->where('user_id', $user->getKey())
                ->value('role'),
        );

        $latestMessages = CookbookMessage::query()
            ->where('cookbook_id', $cookbook->getKey())
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get()
            ->reverse()
            ->values();

        foreach ($latestMessages as $message) {
            $message->setAttribute(
                'member_role',
                $cookbook->members()->whereKey($message->user_id)->value('cookbook_members.role'),
            );
        }

        $data = CookbookResource::make($cookbook->load('owner'))->resolve($request);
        $data['latest_messages'] = CookbookMessageResource::collection($latestMessages)->resolve($request);

        return ApiResponse::success($request, $data);
    }
}
