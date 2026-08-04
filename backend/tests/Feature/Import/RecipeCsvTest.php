<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function csvImportToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->json('data.access_token');
}

function recipeCsv(array $rows): string
{
    $headers = [
        'format_version', 'record_type', 'recipe_key', 'title', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'notes', 'source',
        'ingredient_position', 'ingredient_name', 'ingredient_quantity', 'ingredient_unit', 'ingredient_preparation', 'ingredient_optional', 'ingredient_group', 'step_position', 'step_instruction', 'step_duration_minutes', 'tag',
    ];
    $handle = fopen('php://memory', 'w+');
    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        fputcsv($handle, array_replace(array_fill_keys($headers, ''), $row));
    }
    rewind($handle);

    return stream_get_contents($handle);
}

it('imports recipe, ingredient, step and tag records without JSON fields', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $csv = recipeCsv([
        ['format_version' => '1', 'record_type' => 'recipe', 'recipe_key' => 'r1', 'title' => 'Soupe, maison', 'servings' => '2'],
        ['format_version' => '1', 'record_type' => 'ingredient', 'recipe_key' => 'r1', 'ingredient_position' => '1', 'ingredient_name' => 'Eau', 'ingredient_quantity' => '1.5', 'ingredient_unit' => 'l', 'ingredient_optional' => 'false'],
        ['format_version' => '1', 'record_type' => 'step', 'recipe_key' => 'r1', 'step_position' => '1', 'step_instruction' => 'Cuire, doucement.', 'step_duration_minutes' => '20'],
        ['format_version' => '1', 'record_type' => 'tag', 'recipe_key' => 'r1', 'tag' => 'Hiver'],
    ]);

    $response = $this->withToken(csvImportToken($user))->post('/api/import/csv', ['file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv, 'text/csv')]);
    $response->assertCreated()->assertJsonPath('data.report.recipes', 1);
    $recipe = Recipe::query()->with(['ingredients', 'steps', 'tags'])->firstOrFail();
    expect($recipe->title)->toBe('Soupe, maison')->and($recipe->ingredients[0]->quantity)->toBe('1.500')->and($recipe->steps[0]->instruction)->toBe('Cuire, doucement.')->and($recipe->tags[0]->name)->toBe('Hiver');
});

it('rejects invalid structured values and unknown recipe references', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $csv = recipeCsv([['format_version' => '1', 'record_type' => 'ingredient', 'recipe_key' => 'missing', 'ingredient_position' => 'x', 'ingredient_name' => 'Sel', 'ingredient_optional' => 'yes']]);
    $this->withToken(csvImportToken($user))->post('/api/import/csv', ['file' => UploadedFile::fake()->createWithContent('bad.csv', $csv, 'text/csv')])->assertStatus(422)->assertJsonPath('error.code', 'schema_invalid');
    expect(Recipe::query()->count())->toBe(0);
});

it('keeps JSON import and CSV import on distinct routes and media types', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $this->withToken(csvImportToken($user))->post('/api/import', ['file' => UploadedFile::fake()->createWithContent('recipes.csv', recipeCsv([]), 'text/csv')])->assertStatus(422);
});
