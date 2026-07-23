<?php

namespace App\Services\Cookbook;

use App\Mail\CookbookInvitationMail;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CookbookInvitationService
{
    public function create(Cookbook $cookbook, User $inviter, string $email, string $role): CookbookInvitation
    {
        $target = User::query()->where('email', $email)->first();
        if ($target && $cookbook->members()->whereKey($target->id)->exists()) {
            throw new ConflictHttpException('Cet utilisateur est déjà membre actif de ce cookbook.');
        }

        $token = bin2hex(random_bytes(32));
        $invitation = CookbookInvitation::query()->create([
            'cookbook_id' => $cookbook->id,
            'invited_by' => $inviter->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'role' => $role,
            'expires_at' => now()->addDays((int) config('cookbooks.invitation_ttl_days', 7)),
        ]);

        $invitation->load('cookbook');
        Mail::to($email)->send(new CookbookInvitationMail($invitation, $token));

        return $invitation;
    }

    public function findByToken(string $token): CookbookInvitation
    {
        $invitation = CookbookInvitation::query()->with('cookbook')
            ->where('token_hash', hash('sha256', $token))->firstOrFail();

        /** @var CarbonInterface $expiresAt */
        $expiresAt = $invitation->getAttribute('expires_at');
        if ($invitation->accepted_at !== null || $invitation->declined_at !== null || $expiresAt->isPast()) {
            throw new GoneHttpException('Cette invitation n’est plus valide.');
        }

        return $invitation;
    }

    public function accept(string $token, User $user): CookbookInvitation
    {
        return DB::transaction(function () use ($token, $user): CookbookInvitation {
            $invitation = CookbookInvitation::query()->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->firstOrFail();

            /** @var CarbonInterface $expiresAt */
            $expiresAt = $invitation->getAttribute('expires_at');
            if ($invitation->accepted_at !== null || $invitation->declined_at !== null || $expiresAt->isPast()) {
                throw new GoneHttpException('Cette invitation n’est plus valide.');
            }
            if ($user->email !== $invitation->email) {
                throw new UnauthorizedHttpException('', 'Cette invitation est destinée à une autre adresse email.');
            }
            /** @var Cookbook $cookbook */
            $cookbook = $invitation->cookbook;
            if ($cookbook->members()->whereKey($user->id)->exists()) {
                throw new ConflictHttpException('Cet utilisateur est déjà membre actif de ce cookbook.');
            }

            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
            $cookbook->members()->attach($user, ['role' => $invitation->role, 'joined_at' => now()]);

            return $invitation->fresh(['cookbook']);
        });
    }

    /** @return \Illuminate\Support\Collection<int, CookbookInvitation> */
    public function pendingFor(User $user): \Illuminate\Support\Collection
    {
        return CookbookInvitation::query()
            ->with('cookbook')
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function acceptById(CookbookInvitation $invitation, User $user): CookbookInvitation
    {
        return DB::transaction(function () use ($invitation, $user): CookbookInvitation {
            $invitation = CookbookInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->assertActionableForUser($invitation, $user);
            /** @var Cookbook $cookbook */
            $cookbook = $invitation->cookbook;
            if ($cookbook->members()->whereKey($user->id)->exists()) {
                throw new ConflictHttpException('Cet utilisateur est déjà membre actif de ce cookbook.');
            }
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
            $cookbook->members()->attach($user, ['role' => $invitation->role, 'joined_at' => now()]);
            return $invitation->fresh(['cookbook']);
        });
    }

    public function decline(CookbookInvitation $invitation, User $user): CookbookInvitation
    {
        return DB::transaction(function () use ($invitation, $user): CookbookInvitation {
            $invitation = CookbookInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $this->assertActionableForUser($invitation, $user);
            $invitation->update(['declined_at' => now(), 'declined_by' => $user->id]);
            return $invitation->fresh(['cookbook']);
        });
    }

    private function assertActionableForUser(CookbookInvitation $invitation, User $user): void
    {
        /** @var CarbonInterface $expiresAt */
        $expiresAt = $invitation->getAttribute('expires_at');
        if ($invitation->accepted_at !== null || $invitation->declined_at !== null || $expiresAt->isPast()) {
            throw new GoneHttpException('Cette invitation n’est plus valide.');
        }
        if ($user->email !== $invitation->email) {
            throw new UnauthorizedHttpException('', 'Cette invitation est destinée à une autre adresse email.');
        }
    }
}
