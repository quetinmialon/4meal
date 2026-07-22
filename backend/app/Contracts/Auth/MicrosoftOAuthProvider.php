<?php

namespace App\Contracts\Auth;

use App\Data\Auth\MicrosoftProfile;

interface MicrosoftOAuthProvider extends OAuthProvider
{
    public function profileFromCode(string $code): MicrosoftProfile;
}
