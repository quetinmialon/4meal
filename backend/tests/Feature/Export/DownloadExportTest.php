<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function exportToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function exportJson(TestResponse $response): array
{
    $content = $response->streamedContent();
    /** @var array<string, mixed> $payload */
    $payload = json_decode($content, true);

    return $payload;
}

it('exports only the user-owned and accessible data in versioned SUPMEAL JSON', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create();
    $accessible = Cookbook::factory()->create(['owner_id' => $other->id]);
    $accessible->members()->attach($user, ['role' => 'reader']);
    $foreign = Cookbook::factory()->create(['owner_id' => $other->id]);

    $personal = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $other->id, 'title' => 'À moi']);
    $personal->ingredients()->create(['position' => 1, 'name' => 'Farine', 'quantity' => 200, 'unit' => 'g']);
    $personal->steps()->create(['position' => 1, 'instruction' => 'Mélanger']);
    $shared = Recipe::factory()->inCookbook($accessible)->create(['author_id' => $other->id, 'title' => 'Partagée']);
    $private = Recipe::factory()->inCookbook($foreign)->create(['author_id' => $other->id, 'title' => 'Secrète']);

    $response = $this->withToken(exportToken($user))->get('/api/export');
    $payload = exportJson($response);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('X-4Meal-Export-Warning')
        ->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))->toMatch('/filename=4meal-export-\d{8}-\d{6}\.json/')
        ->and($payload['format'])->toBe('SUPMEAL')
        ->and($payload['version'])->toBe('1.0.0')
        ->and($payload['cookbooks'])->toHaveCount(1)
        ->and($payload['cookbooks'][0]['id'])->toBe('supmeal:cookbook:'.$accessible->public_id)
        ->and($payload['recipes'])->toHaveCount(2)
        ->and(collect($payload['recipes'])->pluck('id'))->not->toContain('supmeal:recipe:'.$private->public_id)
        ->and($payload['recipes'][0]['ingredients'][0]['optional'])->toBeFalse();

    $cookbookRecipeIds = $payload['cookbooks'][0]['recipe_ids'];
    expect($cookbookRecipeIds)->toContain('supmeal:recipe:'.$shared->public_id);
});

it('does not export a cookbook or recipe merely because another user owns it', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $owner = User::factory()->create();
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $payload = exportJson($this->withToken(exportToken($user))->get('/api/export'));

    expect($payload['cookbooks'])->toBeEmpty()->and($payload['recipes'])->toBeEmpty();
});

it('streams large exports in bounded eager-loaded chunks', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    Recipe::factory()->count(105)->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $token = exportToken($user);
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $response = $this->withToken($token)->get('/api/export');

    $response->assertOk();
    expect(exportJson($response)['recipes'])->toHaveCount(105)
        ->and($queries)->toBeLessThan(20);
});
