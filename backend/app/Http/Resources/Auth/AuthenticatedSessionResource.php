<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AuthenticatedSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->sessionPayload();

        return [
            'access_token' => $session['access_token'],
            'token_type' => $session['token_type'],
            'expires_in' => $session['expires_in'],
            'user' => UserResource::make($session['user'])->resolve($request),
        ];
    }

    /**
     * @return array{
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     user: User
     * }
     */
    private function sessionPayload(): array
    {
        $session = $this->resource;

        if (
            ! is_array($session)
            || ! isset($session['access_token'], $session['token_type'], $session['expires_in'], $session['user'])
            || ! is_string($session['access_token'])
            || ! is_string($session['token_type'])
            || ! is_int($session['expires_in'])
            || ! $session['user'] instanceof User
        ) {
            throw new LogicException('Authenticated session resource expects a valid session payload.');
        }

        return $session;
    }
}
