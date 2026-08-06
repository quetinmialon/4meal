<?php

namespace App\Services\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class TwoFactorService
{
    public function issue(User $user): string
    {
        $challenge = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (int) config('two_factor.expires', 10);
        DB::transaction(function () use ($user, $challenge, $code, $expires): void {
            DB::table('two_factor_challenges')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('two_factor_challenges')->insert(['user_id' => $user->id, 'challenge_hash' => hash('sha256', $challenge), 'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')), 'attempts' => 0, 'max_attempts' => (int) config('two_factor.max_attempts', 5), 'expires_at' => now()->addMinutes($expires), 'created_at' => now(), 'updated_at' => now()]);
        });
        Mail::to($user)->send(new TwoFactorCodeMail($user, $code, $expires));

        return $challenge;
    }

    public function verify(string $challenge, string $code): ?User
    {
        return DB::transaction(function () use ($challenge, $code): ?User {
            $record = DB::table('two_factor_challenges')->where('challenge_hash', hash('sha256', $challenge))->lockForUpdate()->first();
            if ($record === null || $record->used_at !== null || now()->greaterThan($record->expires_at) || $record->attempts >= $record->max_attempts) {
                return null;
            }
            if (! hash_equals($record->code_hash, hash_hmac('sha256', $code, (string) config('app.key')))) {
                $attempts = $record->attempts + 1;
                DB::table('two_factor_challenges')->where('id', $record->id)->update(['attempts' => $attempts, 'used_at' => $attempts >= $record->max_attempts ? now() : null, 'updated_at' => now()]);

                return null;
            }
            DB::table('two_factor_challenges')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);

            return User::query()->find($record->user_id);
        });
    }
}
