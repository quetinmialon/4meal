<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeMediaToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function recipeMediaPayload(UploadedFile $image): array
{
    return [
        'title' => 'Recette illustrée',
        'ingredients' => [['name' => 'Farine']],
        'steps' => [['instruction' => 'Mélanger.']],
        'image' => $image,
    ];
}

function recipePngImage(string $name, int $width, int $height): UploadedFile
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

it('stores a recipe image with a generated path and exposes its public URL', function (): void {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->withToken(recipeMediaToken($user))->post('/api/recipes', recipeMediaPayload(
        recipePngImage('original.png', 800, 600),
    ));

    $response->assertCreated()
        ->assertJsonPath('data.image_path', fn (mixed $path): bool => is_string($path) && str_starts_with($path, 'recipes/'))
        ->assertJsonPath('data.image_url', fn (mixed $url): bool => is_string($url));

    $path = Recipe::query()->sole()->image_path;
    expect($path)->toBeString()->toStartWith('recipes/');
    Storage::disk('public')->assertExists($path);
});

it('deletes the previous image when replacing it', function (): void {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    Storage::disk('public')->put('recipes/previous.jpg', 'old image');
    $recipe->update(['image_path' => 'recipes/previous.jpg']);

    $this->withToken(recipeMediaToken($user))
        ->post('/api/recipes/'.$recipe->public_id, [
            '_method' => 'PATCH',
            'image' => recipePngImage('replacement.png', 900, 700),
        ])
        ->assertOk()
        ->assertJsonPath('data.image_url', fn (mixed $url): bool => is_string($url));

    $newPath = $recipe->refresh()->image_path;
    expect($newPath)->toBeString()->not->toBe('recipes/previous.jpg');
    Storage::disk('public')->assertMissing('recipes/previous.jpg');
    Storage::disk('public')->assertExists($newPath);
});

it('rejects dangerous image uploads before storing them', function (): void {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(recipeMediaToken($user))
        ->post('/api/recipes', recipeMediaPayload(
            UploadedFile::fake()->create('payload.php', 100, 'application/x-php'),
        ))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['image']]]]);

    expect(Storage::disk('public')->allFiles('recipes'))->toBe([]);
});

it('rejects images outside the allowed dimensions', function (): void {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(recipeMediaToken($user))
        ->post('/api/recipes', recipeMediaPayload(
            recipePngImage('too-small.png', 50, 50),
        ))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['image']]]]);
});
