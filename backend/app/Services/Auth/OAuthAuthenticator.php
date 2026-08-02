<?php

namespace App\Services\Auth;

use App\Data\Auth\OAuthProfile;
use App\Exceptions\Auth\AmbiguousOAuthAccountException;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OAuthAuthenticator
{
    /** @param class-string<\Throwable> $exceptionClass */
    public function authenticate(OAuthProfile $profile, string $provider, string $exceptionClass): User
    {
        if (! $profile->emailVerified) {
            throw new $exceptionClass("L’adresse e-mail {$provider} doit être vérifiée.");
        }

        return DB::transaction(function () use ($profile, $provider): User {
            $account = OAuthAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $profile->providerId)
                ->lockForUpdate()->first();

            if ($account !== null) {
                $attributes = $this->accountAttributes($profile);
                if ($profile->refreshToken === null) {
                    unset($attributes['refresh_token']);
                }
                $account->update($attributes);

                /** @var User $user */
                $user = $account->user()->firstOrFail();

                return $user;
            }

            if (User::query()->where('email', $profile->email)->exists()) {
                throw new AmbiguousOAuthAccountException($provider);
            }

            $user = User::query()->create(['name' => $profile->name, 'email' => $profile->email]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->oauthAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $profile->providerId,
                ...$this->accountAttributes($profile),
            ]);

            return $user;
        });
    }

    /** @return array<string, mixed> */
    private function accountAttributes(OAuthProfile $profile): array
    {
        return [
            'provider_email' => $profile->email,
            'access_token' => $profile->accessToken,
            'refresh_token' => $profile->refreshToken,
            'token_expires_at' => $profile->expiresIn === null ? null : now()->addSeconds($profile->expiresIn),
        ];
    }
}
