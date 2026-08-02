<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function profileToken(User $user, string $password = 'password123'): string
{
    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk()->json('data.access_token');
}

function profilePngImage(string $name, int $width = 300, int $height = 300): UploadedFile
{
    $png = "\x89PNG\r\n\x1a\n";
    $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
    $rows = str_repeat("\x00".str_repeat("\x00", $width * 4), $height);
    $png .= pack('N', 13).'IHDR'.$header.pack('N', crc32('IHDR'.$header));
    $compressed = zlib_encode($rows, ZLIB_ENCODING_DEFLATE, 9);
    $png .= pack('N', strlen($compressed)).'IDAT'.$compressed.pack('N', crc32('IDAT'.$compressed));
    $png .= pack('N', 0).'IEND'.pack('N', crc32('IEND'));

    return UploadedFile::fake()->createWithContent($name, $png);
}

it('updates the authenticated user profile and returns the resource', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'name' => 'Jane Doe',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonMissingPath('data.password_hash');

    expect($user->fresh()->name)->toBe('Jane Doe');
});

it('requires the current password and resets verification when changing email', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'password' => 'password123',
        'email_verified_at' => now(),
    ]);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'New@Example.com',
    ])->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['current_password']]]]);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'New@Example.com',
        'current_password' => 'password123',
    ])->assertOk()->assertJsonPath('data.email', 'new@example.com');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects an email already used by another user', function () {
    $user = User::factory()->create(['password' => 'password123']);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'TAKEN@example.com',
        'current_password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);
});

it('does not allow sensitive or unknown fields to be updated', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'password_hash' => 'compromised',
        'last_login_at' => now()->toISOString(),
    ])->assertOk();

    expect($user->fresh()->password_hash)->not->toBe('compromised')
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('stores and replaces a profile avatar image', function () {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);
    Storage::disk('public')->put('avatars/old.png', 'old image');
    $user->update(['avatar_path' => 'avatars/old.png']);

    $this->withToken(profileToken($user))->post('/api/auth/me', [
        '_method' => 'PATCH',
        'name' => 'Jane Doe',
        'avatar' => profilePngImage('avatar.png'),
    ])->assertOk()
        ->assertJsonPath('data.avatar_path', fn (mixed $path): bool => is_string($path) && str_starts_with($path, 'avatars/'))
        ->assertJsonPath('data.avatar_url', fn (mixed $url): bool => is_string($url));

    $newPath = $user->fresh()->avatar_path;
    expect($newPath)->toBeString()->not->toBe('avatars/old.png');
    Storage::disk('public')->assertMissing('avatars/old.png');
    Storage::disk('public')->assertExists($newPath);
});

it('rejects non-image profile avatars', function () {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(profileToken($user))->post('/api/auth/me', [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->create('payload.php', 100, 'application/x-php'),
    ])->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['avatar']]]]);
});

it('rejects unauthenticated profile updates', function () {
    $this->patchJson('/api/auth/me', ['name' => 'Jane Doe'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'authentication_error');
});
