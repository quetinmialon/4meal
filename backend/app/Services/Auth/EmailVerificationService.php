<?php

namespace App\Services\Auth;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class EmailVerificationService
{
    public function send(User $user): void
    {
        $token = Str::random(64);
        $expiresInMinutes = (int) config('auth.email_verification.expires', 60);

        DB::transaction(function () use ($user, $token, $expiresInMinutes): void {
            DB::table('email_verification_tokens')->where('user_id', $user->id)->delete();
            DB::table('email_verification_tokens')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes($expiresInMinutes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Mail::to($user)->send(new EmailVerificationMail($user, $token, $expiresInMinutes));
    }

    public function verify(User $user, string $token): bool
    {
        return DB::transaction(function () use ($user, $token): bool {
            $record = DB::table('email_verification_tokens')
                ->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();

            if ($record === null || now()->greaterThan($record->expires_at)) {
                return false;
            }

            DB::table('email_verification_tokens')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return true;
        });
    }
}
