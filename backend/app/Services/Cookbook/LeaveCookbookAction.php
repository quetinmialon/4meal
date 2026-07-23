<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeaveCookbookAction
{
    public function execute(Cookbook $cookbook, User $member): void
    {
        DB::transaction(function () use ($cookbook, $member): void {
            $cookbook = Cookbook::query()
                ->whereKey($cookbook->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $membership = CookbookMember::query()
                ->where('cookbook_id', $cookbook->getKey())
                ->where('user_id', $member->getKey())
                ->lockForUpdate()
                ->first();

            if (! $membership instanceof CookbookMember) {
                throw new NotFoundHttpException('Vous n’êtes pas membre de ce cookbook.');
            }

            if ((int) $cookbook->owner_id === (int) $member->getKey()) {
                throw new ConflictHttpException('Le propriétaire doit transférer la propriété avant de quitter le cookbook.');
            }

            $membership->delete();
        });
    }
}
