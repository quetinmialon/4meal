<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use App\Support\CookbookPermissions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChangeCookbookMemberRoleAction
{
    public function execute(Cookbook $cookbook, User $actor, User $target, string $role): CookbookMember
    {
        return DB::transaction(function () use ($cookbook, $actor, $target, $role): CookbookMember {
            $cookbook = Cookbook::query()
                ->whereKey($cookbook->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $member = CookbookMember::query()
                ->where('cookbook_id', $cookbook->getKey())
                ->where('user_id', $target->getKey())
                ->lockForUpdate()
                ->first();

            if (! $member instanceof CookbookMember) {
                throw new NotFoundHttpException('Cet utilisateur n’est pas membre de ce cookbook.');
            }

            $currentRole = (string) $member->role;

            if ((int) $actor->getKey() === (int) $target->getKey() && $role !== $currentRole) {
                throw new ConflictHttpException('Un membre ne peut pas modifier son propre rôle.');
            }

            if ((int) $cookbook->owner_id === (int) $target->getKey() && $role !== CookbookPermissions::OWNER) {
                throw new ConflictHttpException('Le propriétaire ne peut pas être dégradé.');
            }

            if ($role === CookbookPermissions::OWNER
                && (int) $cookbook->owner_id !== (int) $target->getKey()) {
                $currentOwner = CookbookMember::query()
                    ->where('cookbook_id', $cookbook->getKey())
                    ->where('user_id', $cookbook->owner_id)
                    ->lockForUpdate()
                    ->first();

                if (! $currentOwner instanceof CookbookMember) {
                    throw new ConflictHttpException('Le propriétaire actuel est introuvable parmi les membres.');
                }

                $currentOwner->update(['role' => 'editor']);
                $member->update(['role' => CookbookPermissions::OWNER]);
                $cookbook->update(['owner_id' => $target->getKey()]);

                return $member->fresh(['user']);
            }

            if ($currentRole === CookbookPermissions::OWNER && $role !== CookbookPermissions::OWNER) {
                $ownerCount = CookbookMember::query()
                    ->where('cookbook_id', $cookbook->getKey())
                    ->where('role', CookbookPermissions::OWNER)
                    ->count();

                if ($ownerCount <= 1) {
                    throw new ConflictHttpException('Le dernier owner ne peut pas être retiré.');
                }
            }

            $member->update(['role' => $role]);

            return $member->fresh(['user']);
        });
    }
}
