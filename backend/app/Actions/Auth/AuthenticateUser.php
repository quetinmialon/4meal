<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticateUser
{
    public function handle(string $email, string $password): ?User
    {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null || ! is_string($user->getAttribute('password_hash')) || ! Hash::check($password, $user->getAttribute('password_hash'))) {
            return null;
        }

        return $user;
    }
}
