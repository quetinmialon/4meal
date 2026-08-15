<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('analyses a SUPMEAL import and never writes to the database', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = importToken($user);
    $before = [Cookbook::query()->count(), Recipe::query()->count()];

    $response = $this->withToken($token)->post('/api/import/preview', ['file' => importFile(importDocument())]);

    $response->assertOk()
        ->assertJsonPath('data.analysis.objects.0.type', 'recipe')
        ->assertJsonPath('data.analysis.objects.0.title', 'Omelette nature')
        ->assertJsonPath('data.analysis.warnings', [])
        ->assertJsonPath('data.analysis.errors', [])
        ->assertJsonPath('data.analysis.duplicates', []);
    expect([Cookbook::query()->count(), Recipe::query()->count()])->toBe($before);
});

it('reports existing objects as potential duplicates without writing', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    Recipe::factory()->for($user)->create(['title' => 'Omelette nature', 'source' => null]);

    $response = $this->withToken(importToken($user))->post('/api/import/preview', ['file' => importFile(importDocument())]);

    $response->assertOk()
        ->assertJsonPath('data.analysis.duplicates.0.path', 'recipes.0')
        ->assertJsonPath('data.analysis.duplicates.0.type', 'recipe');
    expect(Recipe::query()->where('title', 'Omelette nature')->count())->toBe(1);
});

it('returns validation errors and still does not write', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $before = [Cookbook::query()->count(), Recipe::query()->count()];
    $document = importDocument(['recipes' => [['id' => 'bad', 'title' => 'Invalide']]]);

    $response = $this->withToken(importToken($user))->post('/api/import/preview', ['file' => importFile($document)]);

    $response->assertOk()->assertJsonPath('data.analysis.objects', [])->assertJsonPath('data.analysis.errors.0.code', 'schema_invalid');
    expect([Cookbook::query()->count(), Recipe::query()->count()])->toBe($before);
});
