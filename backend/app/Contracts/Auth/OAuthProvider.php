<?php

namespace App\Contracts\Auth;

use App\Data\Auth\OAuthProfile;

interface OAuthProvider
{
    public function authorizationUrl(string $state): string;

    public function profileFromCode(string $code): OAuthProfile;
}
