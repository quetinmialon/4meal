<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function updateRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function updateRecipe(User $user): Recipe
{
    $recipe = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Titre initial',
        'prep_time_minutes' => 10,
        'cook_time_minutes' => 20,
        'servings' => 2,
        'source' => 'Source initiale',
    ]);
    $recipe->ingredients()->create(['position' => 1, 'name' => 'Farine', 'quantity' => 100, 'unit' => 'g']);
    $recipe->steps()->create(['position' => 1, 'instruction' => 'Mélanger.', 'duration_minutes' => 5]);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Initial', 'slug' => 'initial']);
    $recipe->tags()->attach($tag);

    return $recipe;
}

it('updates only the supplied fields and keeps omitted relations unchanged', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = updateRecipe($user);

    $this->withToken(updateRecipeToken($user))
        ->patchJson('/api/recipes/'.$recipe->public_id, [
            'title' => 'Titre partiellement modifié',
            'prep_time_minutes' => 15,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Titre partiellement modifié')
        ->assertJsonPath('data.prep_time_minutes', 15);

    $recipe->refresh();
    expect($recipe->cook_time_minutes)->toBe(20)
        ->and($recipe->servings)->toBe(2)
        ->and($recipe->ingredients()->count())->toBe(1)
        ->and($recipe->steps()->count())->toBe(1)
        ->and($recipe->tags()->count())->toBe(1);
});

it('synchronizes all recipe fields, ingredients, steps and tags', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = updateRecipe($user);

    $this->withToken(updateRecipeToken($user))
        ->patchJson('/api/recipes/'.$recipe->public_id, [
            'title' => 'Titre complet',
            'description' => 'Nouvelle description',
            'prep_time_minutes' => 25,
            'cook_time_minutes' => 40,
            'servings' => 4,
            'source' => 'Nouvelle source',
            'ingredients' => [
                ['name' => 'Tomates', 'quantity' => 3, 'unit' => 'pièces'],
                ['name' => 'Huile', 'quantity' => 2, 'unit' => 'cuillères', 'is_optional' => true],
            ],
            'steps' => [
                ['instruction' => 'Découper les tomates.'],
                ['instruction' => 'Ajouter l’huile.', 'duration_minutes' => 2],
            ],
            'tags' => ['Rapide', 'Végétarien'],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Titre complet')
        ->assertJsonCount(2, 'data.ingredients')
        ->assertJsonCount(2, 'data.steps')
        ->assertJsonCount(2, 'data.tags');

    $recipe->refresh();
    expect($recipe->ingredients()->pluck('name')->all())->toBe(['Tomates', 'Huile'])
        ->and($recipe->steps()->pluck('instruction')->all())->toBe(['Découper les tomates.', 'Ajouter l’huile.'])
        ->and($recipe->tags()->pluck('slug')->sort()->values()->all())->toBe(['rapide', 'vegetarien']);
    $this->assertDatabaseMissing('recipe_ingredients', ['name' => 'Farine']);
    $this->assertDatabaseMissing('recipe_steps', ['instruction' => 'Mélanger.']);
    expect($recipe->tags()->where('slug', 'initial')->exists())->toBeFalse();
});

it('rolls back the complete update when synchronizing children fails', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = updateRecipe($user);

    $this->withToken(updateRecipeToken($user))
        ->patchJson('/api/recipes/'.$recipe->public_id, [
            'title' => 'Ne doit pas être enregistré',
            'ingredients' => [
                ['position' => 1, 'name' => 'Premier'],
                ['position' => 1, 'name' => 'Doublon'],
            ],
        ])
        ->assertServerError();

    $recipe->refresh();
    expect($recipe->title)->toBe('Titre initial')
        ->and($recipe->ingredients()->pluck('name')->all())->toBe(['Farine'])
        ->and($recipe->steps()->count())->toBe(1)
        ->and($recipe->tags()->count())->toBe(1);
});

it('follows cookbook permissions for recipe updates', function (string $role, bool $allowed): void {
    $owner = User::factory()->create();
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => $role]);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $response = $this->withToken(updateRecipeToken($member))
        ->patchJson('/api/recipes/'.$recipe->public_id, ['title' => 'Modification cookbook']);

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    ['editor', true],
    ['reader', false],
]);
