<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function importToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

/** @param array<string, mixed> $overrides */
function importDocument(array $overrides = []): array
{
    return array_replace_recursive([
        'format' => 'SUPMEAL',
        'version' => '1.0.0',
        'exported_at' => '2026-07-30T12:00:00Z',
        'cookbooks' => [],
        'recipes' => [[
            'id' => 'supmeal:recipe:omelette',
            'title' => 'Omelette nature',
            'description' => null,
            'servings' => 1,
            'prep_time_minutes' => 2,
            'cook_time_minutes' => 3,
            'rest_time_minutes' => null,
            'ingredients' => [[
                'name' => 'Œuf', 'quantity' => 2, 'unit' => 'pièce', 'preparation' => null,
                'optional' => false, 'group' => null,
            ]],
            'steps' => [[
                'position' => 1, 'instruction' => 'Battre puis cuire.', 'duration_minutes' => 3, 'image_url' => null,
            ]],
            'tags' => ['rapide'],
            'cookbook_ids' => [],
        ]],
    ], $overrides);
}

function importFile(array $document): UploadedFile
{
    return UploadedFile::fake()->createWithContent('recipes.json', json_encode($document, JSON_THROW_ON_ERROR), 'application/json');
}

it('imports a valid SUPMEAL document and assigns ownership to the authenticated user', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create();

    $response = $this->withToken(importToken($user))->post('/api/import', ['file' => importFile(importDocument())]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.report.recipes', 1);
    $recipe = Recipe::query()->firstOrFail();
    expect($recipe->user_id)->toBe($user->id)
        ->and($recipe->author_id)->toBe($user->id)
        ->and($recipe->public_id)->not->toBe('supmeal:recipe:omelette')
        ->and($recipe->user_id)->not->toBe($other->id);
});

it('imports SUPMEAL recipes with empty ingredient and step collections', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $document = importDocument([
        'recipes' => [[
            'id' => 'supmeal:recipe:empty', 'title' => 'Recette à compléter', 'description' => null,
            'servings' => null, 'prep_time_minutes' => null, 'cook_time_minutes' => null, 'rest_time_minutes' => null,
            'ingredients' => [], 'steps' => [], 'tags' => [], 'cookbook_ids' => [],
        ]],
    ]);

    $this->withToken(importToken($user))->post('/api/import', ['file' => importFile($document)])
        ->assertCreated()
        ->assertJsonPath('data.report.recipes', 1);
});

it('imports cookbooks through internal mappings and never trusts external ids', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $document = importDocument([
        'cookbooks' => [[
            'id' => 'supmeal:cookbook:external-999', 'name' => 'Famille', 'slug' => 'famille',
            'description' => 'Partagé', 'recipe_ids' => ['supmeal:recipe:omelette'],
        ]],
        'recipes' => [[
            'id' => 'supmeal:recipe:omelette', 'title' => 'Omelette nature', 'description' => null,
            'servings' => 1, 'prep_time_minutes' => 2, 'cook_time_minutes' => 3, 'rest_time_minutes' => null,
            'ingredients' => [['name' => 'Œuf', 'quantity' => 2, 'unit' => 'pièce', 'preparation' => null, 'optional' => false, 'group' => null]],
            'steps' => [['position' => 1, 'instruction' => 'Cuire.', 'duration_minutes' => 3, 'image_url' => null]],
            'tags' => [], 'cookbook_ids' => ['supmeal:cookbook:external-999'],
        ]],
    ]);

    $this->withToken(importToken($user))->post('/api/import', ['file' => importFile($document)])->assertCreated();

    $cookbook = Cookbook::query()->firstOrFail();
    $recipe = Recipe::query()->firstOrFail();
    expect($cookbook->public_id)->not->toBe('external-999')
        ->and($cookbook->owner_id)->toBe($user->id)
        ->and($recipe->cookbook_id)->toBe($cookbook->id)
        ->and($recipe->author_id)->toBe($user->id);
});

it('rejects invalid JSON and schema documents with safe detailed errors', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $invalid = UploadedFile::fake()->createWithContent('bad.json', '{"format":"SUPMEAL","version":"9.0.0"}');

    $this->withToken(importToken($user))
        ->post('/api/import', ['file' => $invalid])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'schema_invalid')
        ->assertJsonPath('error.details.errors.0.code', 'schema_invalid');
});

it('rejects files that are too large or have the wrong MIME type', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = importToken($user);

    $this->withToken($token)
        ->post('/api/import', ['file' => UploadedFile::fake()->create('recipes.json', 10241, 'application/json')])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    $this->withToken($token)
        ->post('/api/import', ['file' => UploadedFile::fake()->create('recipes.json', 10, 'text/plain')])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('rolls back cookbooks and recipes when persistence fails', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    Recipe::creating(function (): void {
        throw new RuntimeException('forced test failure');
    });
    $document = importDocument([
        'cookbooks' => [[
            'id' => 'supmeal:cookbook:family', 'name' => 'Famille', 'slug' => 'famille', 'description' => null,
            'recipe_ids' => ['supmeal:recipe:omelette'],
        ]],
        'recipes' => [[
            'id' => 'supmeal:recipe:omelette', 'title' => 'Dans le cookbook', 'description' => null,
            'servings' => 1, 'prep_time_minutes' => 1, 'cook_time_minutes' => 1, 'rest_time_minutes' => null,
            'ingredients' => [['name' => 'Œuf', 'quantity' => 1, 'unit' => null, 'preparation' => null, 'optional' => false, 'group' => null]],
            'steps' => [['position' => 1, 'instruction' => 'Cuire.', 'duration_minutes' => 1, 'image_url' => null]],
            'tags' => [], 'cookbook_ids' => ['supmeal:cookbook:family'],
        ]],
    ]);

    $this->withToken(importToken($user))
        ->post('/api/import', ['file' => importFile($document)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'import_failed')
        ->assertJsonPath('error.details.errors.0.code', 'transaction_failed');

    expect(Cookbook::query()->count())->toBe(0)->and(Recipe::query()->count())->toBe(0);
});

it('requires authentication to import', function (): void {
    $this->post('/api/import', ['file' => importFile(importDocument())])->assertUnauthorized();
});
