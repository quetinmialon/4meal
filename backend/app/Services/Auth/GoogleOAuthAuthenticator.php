<?php

namespace App\Services\Auth;

use App\Data\Auth\GoogleProfile;
use App\Exceptions\Auth\AmbiguousOAuthAccountException;
use App\Exceptions\Auth\GoogleOAuthException;
use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GoogleOAuthAuthenticator
{
    public function authenticate(GoogleProfile $profile): User
    {
        if (! $profile->emailVerified) {
            throw new GoogleOAuthException('L’adresse e-mail Google doit être vérifiée.');
        }

        return DB::transaction(function () use ($profile): User {
            $account = OAuthAccount::query()
                ->where('provider', 'google')
                ->where('provider_user_id', $profile->providerId)
                ->lockForUpdate()
                ->first();

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
                throw new AmbiguousOAuthAccountException;
            }

            $user = User::query()->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'password' => Str::random(64),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->oauthAccounts()->create([
                'provider' => 'google',
                'provider_user_id' => $profile->providerId,
                ...$this->accountAttributes($profile),
            ]);

            return $user;
        });
    }

    /** @return array<string, mixed> */
    private function accountAttributes(GoogleProfile $profile): array
    {
        return [
            'provider_email' => $profile->email,
            'access_token' => $profile->accessToken,
            'refresh_token' => $profile->refreshToken,
            'token_expires_at' => $profile->expiresIn === null ? null : now()->addSeconds($profile->expiresIn),
        ];
    }
}
