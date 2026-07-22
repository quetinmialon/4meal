<?php

namespace App\Services\Auth;

use App\Data\Auth\MicrosoftProfile;
use App\Exceptions\Auth\MicrosoftOAuthException;
use App\Models\User;

final class MicrosoftOAuthAuthenticator
{
    public function __construct(private readonly OAuthAuthenticator $authenticator) {}

    public function authenticate(MicrosoftProfile $profile): User
    {
        return $this->authenticator->authenticate($profile, 'microsoft', MicrosoftOAuthException::class);
    }
}
