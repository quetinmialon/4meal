<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function csvExportToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->json('data.access_token');
}

it('exports structured recipe records in the documented CSV format', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id, 'title' => 'Crème, citron']);
    $recipe->ingredients()->create(['position' => 1, 'name' => 'Citron', 'quantity' => 2.5, 'unit' => 'pièce', 'is_optional' => true]);
    $recipe->steps()->create(['position' => 1, 'instruction' => 'Mélanger, puis cuire.', 'duration_minutes' => 10]);
    $recipe->tags()->create(['name' => 'Rapide', 'slug' => 'rapide', 'user_id' => $user->id]);

    $response = $this->withToken(csvExportToken($user))->get('/api/export/csv');
    $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($response->streamedContent())));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($rows[0])->toBe([
        'format_version', 'record_type', 'recipe_key', 'title', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'notes', 'source',
        'ingredient_position', 'ingredient_name', 'ingredient_quantity', 'ingredient_unit', 'ingredient_preparation', 'ingredient_optional', 'ingredient_group', 'step_position', 'step_instruction', 'step_duration_minutes', 'tag',
    ])->and(collect($rows)->pluck(1)->all())->toBe(['record_type', 'recipe', 'ingredient', 'step', 'tag']);
});

it('does not export recipes outside the authenticated accessible scope', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $foreign = Recipe::factory()->create(['user_id' => User::factory()->create()->id, 'author_id' => $user->id, 'title' => 'Privée']);
    $content = $this->withToken(csvExportToken($user))->get('/api/export/csv')->streamedContent();
    expect($content)->not->toContain($foreign->title);
});
