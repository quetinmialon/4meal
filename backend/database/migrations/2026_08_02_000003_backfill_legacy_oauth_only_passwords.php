<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotNull('password_hash')
            ->with('oauthAccounts:id,user_id,created_at')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $account = $user->oauthAccounts->sortBy('created_at')->first();
                    if ($account === null || $user->created_at === null || $account->created_at === null) {
                        continue;
                    }

                    if (abs($user->created_at->diffInSeconds($account->created_at, false)) <= 5) {
                        $user->forceFill(['password_hash' => null])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        // The previous random OAuth password cannot be recovered safely.
    }
};
