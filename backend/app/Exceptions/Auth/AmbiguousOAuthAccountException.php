<?php

namespace App\Exceptions\Auth;

use RuntimeException;

final class AmbiguousOAuthAccountException extends RuntimeException
{
    public function __construct(string $provider = 'Google')
    {
        parent::__construct("Un compte existe déjà avec cette adresse e-mail. Connectez-vous avec votre mot de passe avant de lier {$provider}.");
    }
}
