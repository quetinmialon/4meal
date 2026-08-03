<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('returns the same response for known and unknown email addresses', function () {
    Mail::fake();
    User::factory()->create(['email' => 'known@example.com']);

    $known = $this->postJson('/api/auth/password/email', ['email' => 'known@example.com']);
    $unknown = $this->postJson('/api/auth/password/email', ['email' => 'unknown@example.com']);

    $known->assertStatus(202)->assertJsonPath('success', true)->assertJsonPath(
        'data.message',
        $unknown->json('data.message'),
    );
    Mail::assertSent(PasswordResetMail::class, 1);
});

it('sends a temporary token and resets the password once', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'known@example.com', 'password' => 'old-password123']);

    $this->postJson('/api/auth/password/email', ['email' => $user->email])->assertStatus(202);

    $token = null;
    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token, $user): bool {
        $token = $mail->token;

        return $mail->user->is($user) && strlen($mail->token) >= 32;
    });

    expect($token)->toBeString();
    expect(DB::table('password_reset_tokens')->value('token'))
        ->toBe(hash('sha256', $token));

    $this->postJson('/api/auth/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'new-password123',
    ])->assertOk();

    $this->postJson('/api/auth/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'another-password123',
        'password_confirmation' => 'another-password123',
    ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
});

it('invalidates all jwt sessions after a password reset', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'known@example.com', 'password' => 'old-password123']);
    $firstToken = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'old-password123',
    ])->json('data.access_token');
    $secondToken = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'old-password123',
    ])->json('data.access_token');

    $this->postJson('/api/auth/password/email', ['email' => $user->email]);
    $token = null;
    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    $this->postJson('/api/auth/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk();

    $this->withToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
    $this->withToken($secondToken)->getJson('/api/auth/me')->assertUnauthorized();
});

it('rate limits password reset requests', function () {
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/auth/password/email', [
            'email' => 'unknown@example.com',
        ])->assertStatus(202);
    }

    $this->postJson('/api/auth/password/email', [
        'email' => 'unknown@example.com',
    ])->assertStatus(429);
});

it('rejects expired reset tokens', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'known@example.com']);

    $this->postJson('/api/auth/password/email', ['email' => $user->email]);
    $token = null;
    Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    $this->travel(61)->minutes();

    $this->postJson('/api/auth/password/reset', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertStatus(422);
});
