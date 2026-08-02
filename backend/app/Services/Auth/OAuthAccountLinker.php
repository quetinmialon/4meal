<?php

namespace App\Services\Auth;

use App\Data\Auth\OAuthProfile;
use App\Exceptions\Auth\OAuthAccountAlreadyLinkedException;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OAuthAccountLinker
{
    public function link(User $user, OAuthProfile $profile, string $provider): OAuthAccount
    {
        return DB::transaction(function () use ($user, $profile, $provider): OAuthAccount {
            $account = OAuthAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $profile->providerId)
                ->lockForUpdate()
                ->first();

            if ($account !== null && (int) $account->user_id !== (int) $user->getKey()) {
                throw new OAuthAccountAlreadyLinkedException('Ce compte OAuth est déjà associé à un autre utilisateur.');
            }

            $providerAccount = $user->oauthAccounts()
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();

            if ($providerAccount !== null && ($account === null || $providerAccount->getKey() !== $account->getKey())) {
                throw new OAuthAccountAlreadyLinkedException('Un compte '.$provider.' est déjà associé à cet utilisateur.');
            }

            $attributes = [
                'provider' => $provider,
                'provider_user_id' => $profile->providerId,
                'provider_email' => $profile->email,
                'access_token' => $profile->accessToken,
                'refresh_token' => $profile->refreshToken,
                'token_expires_at' => $profile->expiresIn === null ? null : now()->addSeconds($profile->expiresIn),
            ];

            if ($account === null) {
                /** @var OAuthAccount $created */
                $created = $user->oauthAccounts()->create($attributes);

                return $created;
            }

            if ($profile->refreshToken === null) {
                unset($attributes['refresh_token']);
            }
            $account->update($attributes);

            /** @var OAuthAccount $refreshed */
            $refreshed = OAuthAccount::query()->findOrFail($account->getKey());

            return $refreshed;
        });
    }
}
