<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('imports a realistic Mealie recipe without changing the SUPMEAL model', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    config()->set('jwt.secret', Str::repeat('a', 64));
    $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->json('data.access_token');
    $fixture = file_get_contents(base_path('tests/Fixtures/mealie/carbonara.json'));

    $this->withToken($token)->post('/api/import/mealie', ['file' => UploadedFile::fake()->createWithContent('carbonara.json', $fixture, 'application/json')])->assertCreated()->assertJsonPath('data.report.recipes', 1);

    $recipe = Recipe::query()->with(['ingredients', 'steps', 'tags'])->firstOrFail();
    expect($recipe->title)->toBe('Pâtes Carbonara')->and($recipe->servings)->toBe(4)->and($recipe->prep_time_minutes)->toBe(10)->and($recipe->cook_time_minutes)->toBe(15)->and($recipe->ingredients[0]->name)->toBe('Spaghetti')->and($recipe->ingredients[3]->preparation)->toBe('râpé')->and($recipe->steps[0]->instruction)->toBe('Cuisson: Faire cuire les pâtes.')->and($recipe->tags->pluck('name')->all())->toEqualCanonicalizing(['Plat principal', 'Italie', 'Pâtes']);
});

it('rejects a Mealie recipe without ingredients atomically', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    config()->set('jwt.secret', Str::repeat('a', 64));
    $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->json('data.access_token');
    $document = ['name' => 'Incomplète', 'recipeIngredient' => [], 'recipeInstructions' => [['text' => 'Cuire.']]];

    $this->withToken($token)->post('/api/import/mealie', ['file' => UploadedFile::fake()->createWithContent('bad.json', json_encode($document), 'application/json')])->assertStatus(422)->assertJsonPath('error.code', 'schema_invalid');
    expect(Recipe::query()->count())->toBe(0);
});
