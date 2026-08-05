<?php

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers users as unverified and sends a one-time expiring token', function () {
    Mail::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe', 'email' => 'jane@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    $response->assertJsonPath('data.email_verified', false);
    Mail::assertSent(EmailVerificationMail::class, fn (EmailVerificationMail $mail): bool => $mail->user->is($user));
    expect((int) DB::table('email_verification_tokens')->where('user_id', $user->id)->count())->toBe(1);
});

it('verifies an email token once and rejects it after expiration or reuse', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create(['password' => 'password']);
    $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.access_token');
    $this->withToken($token)->postJson('/api/auth/email/verification-notification')->assertAccepted();
    $token = null;
    Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    $this->getJson('/api/auth/email/verify/'.$user->id.'/'.$token)
        ->assertOk()->assertJsonPath('data.user.email', $user->email);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $this->getJson('/api/auth/email/verify/'.$user->id.'/'.$token)->assertUnprocessable();
});

it('rejects an expired verification token', function () {
    Mail::fake();
    Carbon::setTestNow('2026-08-05 12:00:00');
    JWT::$timestamp = Carbon::now()->timestamp;
    try {
        $user = User::factory()->unverified()->create(['password' => 'password']);
        $loginToken = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.access_token');
        $this->withToken($loginToken)->postJson('/api/auth/email/verification-notification')->assertAccepted();
        $token = null;
        Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        Carbon::setTestNow(Carbon::now()->addMinutes(61));
        $this->getJson('/api/auth/email/verify/'.$user->id.'/'.$token)->assertUnprocessable();
        expect($user->fresh()->email_verified_at)->toBeNull();
    } finally {
        JWT::$timestamp = null;
        Carbon::setTestNow();
    }
});

it('forbids business features until verification while allowing identity and verification flows', function () {
    $user = User::factory()->unverified()->create(['password' => 'password123']);
    $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();
    $token = $login->json('data.access_token');

    $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
    $this->withToken($token)->getJson('/api/recipes')->assertForbidden()
        ->assertJsonPath('error.code', 'email_not_verified')
        ->assertJsonPath('error.details.forbidden_until_verification', true);
});

it('limits verification email resends', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.access_token');
    $this->withToken($token)->postJson('/api/auth/email/verification-notification')->assertAccepted();
    $this->withToken($token)->postJson('/api/auth/email/verification-notification')->assertTooManyRequests();
});
