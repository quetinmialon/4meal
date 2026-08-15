<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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

it('previews CSV imports without writing', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $headers = ['format_version', 'record_type', 'recipe_key', 'title', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'notes', 'source', 'ingredient_position', 'ingredient_name', 'ingredient_quantity', 'ingredient_unit', 'ingredient_preparation', 'ingredient_optional', 'ingredient_group', 'step_position', 'step_instruction', 'step_duration_minutes', 'tag'];
    $row = array_fill_keys($headers, '');
    $row['format_version'] = '1';
    $row['record_type'] = 'recipe';
    $row['recipe_key'] = 'r1';
    $row['title'] = 'Soupe';
    $handle = fopen('php://memory', 'w+');
    fputcsv($handle, $headers);
    fputcsv($handle, array_values($row));
    rewind($handle);
    $file = UploadedFile::fake()->createWithContent('recipes.csv', stream_get_contents($handle), 'text/csv');
    $before = Recipe::query()->count();

    $response = $this->withToken(importToken($user))->post('/api/import/preview/csv', ['file' => $file]);

    $response->assertOk()->assertJsonPath('data.analysis.objects.0.title', 'Soupe')->assertJsonPath('data.analysis.errors', []);
    expect(Recipe::query()->count())->toBe($before);
});

it('previews Mealie imports without writing', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $file = UploadedFile::fake()->createWithContent('mealie.json', file_get_contents(base_path('tests/Fixtures/mealie/carbonara.json')), 'application/json');
    $before = Recipe::query()->count();

    $response = $this->withToken(importToken($user))->post('/api/import/preview/mealie', ['file' => $file]);

    $response->assertOk()->assertJsonPath('data.analysis.objects.0.title', 'Pâtes Carbonara')->assertJsonPath('data.analysis.errors', []);
    expect(Recipe::query()->count())->toBe($before);
});
