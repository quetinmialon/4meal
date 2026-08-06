<?php

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('can activate and deactivate 2FA only with the current password', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->json('data.access_token');

    $this->withToken($token)->postJson('/api/auth/2fa/enable')->assertOk();
    expect($user->fresh()->two_factor_enabled)->toBeTrue();

    $this->withToken($token)->postJson('/api/auth/2fa/disable', ['current_password' => 'wrong'])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/auth/2fa/disable', ['current_password' => 'password123'])->assertOk();
    expect($user->fresh()->two_factor_enabled)->toBeFalse();
});

it('requires an emailed temporary code and invalidates it after use', function () {
    Mail::fake();
    $user = User::factory()->create(['password' => 'password123', 'two_factor_enabled' => true]);

    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123']);
    $login->assertStatus(202)->assertJsonPath('data.two_factor_required', true);
    $challenge = $login->json('data.challenge');
    $code = null;
    Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $this->postJson('/api/auth/2fa/verify', ['challenge' => $challenge, 'code' => $code])->assertOk();
    $this->postJson('/api/auth/2fa/verify', ['challenge' => $challenge, 'code' => $code])->assertUnauthorized();
});

it('expires a code and permanently locks a challenge after too many wrong attempts', function () {
    Mail::fake();
    $user = User::factory()->create(['password' => 'password123', 'two_factor_enabled' => true]);
    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123']);
    $challenge = $login->json('data.challenge');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/auth/2fa/verify', ['challenge' => $challenge, 'code' => '000000'])->assertUnauthorized();
    }
    $this->postJson('/api/auth/2fa/verify', ['challenge' => $challenge, 'code' => '000000'])->assertUnauthorized();
    expect(DB::table('two_factor_challenges')->where('challenge_hash', hash('sha256', $challenge))->value('used_at'))->not->toBeNull();

    $expiredLogin = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123']);
    $expiredChallenge = $expiredLogin->json('data.challenge');
    Carbon::setTestNow(now()->addMinutes(11));
    try {
        $this->postJson('/api/auth/2fa/verify', ['challenge' => $expiredChallenge, 'code' => '000000'])->assertUnauthorized();
    } finally {
        Carbon::setTestNow();
    }
});
