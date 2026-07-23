<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RemoveCookbookMemberAction
{
    public function execute(Cookbook $cookbook, User $actor, User $target): void
    {
        DB::transaction(function () use ($cookbook, $actor, $target): void {
            $cookbook = Cookbook::query()
                ->whereKey($cookbook->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $membership = CookbookMember::query()
                ->where('cookbook_id', $cookbook->getKey())
                ->where('user_id', $target->getKey())
                ->lockForUpdate()
                ->first();

            if (! $membership instanceof CookbookMember) {
                throw new NotFoundHttpException('Cet utilisateur n’est pas membre de ce cookbook.');
            }

            if ((int) $actor->getKey() === (int) $target->getKey()) {
                throw new ConflictHttpException('Utilisez l’action quitter pour supprimer votre propre adhésion.');
            }

            if ((int) $cookbook->owner_id === (int) $target->getKey()
                || $membership->role === 'owner') {
                throw new ConflictHttpException('Le propriétaire ne peut pas être retiré du cookbook.');
            }

            $membership->delete();
        });
    }
}
