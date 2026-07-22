<?php

namespace App\Services\Auth;

use App\Data\Auth\GoogleProfile;
use App\Exceptions\Auth\GoogleOAuthException;
use App\Models\User;

final class GoogleOAuthAuthenticator
{
    public function __construct(private readonly OAuthAuthenticator $authenticator) {}

    public function authenticate(GoogleProfile $profile): User
    {
        return $this->authenticator->authenticate($profile, 'google', GoogleOAuthException::class);
    }
}
