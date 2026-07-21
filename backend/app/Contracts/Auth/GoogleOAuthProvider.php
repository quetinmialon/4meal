<?php

namespace App\Contracts\Auth;

use App\Data\Auth\GoogleProfile;

interface GoogleOAuthProvider
{
    public function authorizationUrl(string $state): string;

    public function profileFromCode(string $code): GoogleProfile;
}
