<?php

namespace App\Contracts\Auth;

use App\Data\Auth\GoogleProfile;

interface GoogleOAuthProvider extends OAuthProvider
{
    public function profileFromCode(string $code): GoogleProfile;
}
