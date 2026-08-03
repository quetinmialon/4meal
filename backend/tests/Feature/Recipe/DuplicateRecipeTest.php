<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function duplicateRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function duplicateSourceRecipe(User $owner): Recipe
{
    $recipe = Recipe::factory()->create([
        'user_id' => $owner->id,
        'author_id' => $owner->id,
        'title' => 'Recette à copier',
        'description' => 'Description',
        'rest_time_minutes' => 15,
        'notes' => 'Notes',
        'source' => 'Carnet',
    ]);
    $recipe->ingredients()->create([
        'position' => 1, 'name' => 'Farine', 'quantity' => 200, 'unit' => 'g',
        'preparation' => 'tamisée', 'is_optional' => false, 'group_name' => 'Pâte',
    ]);
    $recipe->steps()->create([
        'position' => 1, 'instruction' => 'Mélanger.', 'duration_minutes' => 5,
    ]);
    $tag = Tag::factory()->create(['user_id' => $owner->id, 'name' => 'Dessert', 'slug' => 'dessert']);
    $recipe->tags()->attach($tag);

    return $recipe;
}

it('duplicates all recipe data into the user personal space without the image', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $source = duplicateSourceRecipe($owner);
    $source->update(['image_path' => 'recipes/original.jpg']);

    $response = $this->withToken(duplicateRecipeToken($owner))
        ->postJson('/api/recipes/'.$source->public_id.'/duplicate');

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Recette à copier')
        ->assertJsonPath('data.description', 'Description')
        ->assertJsonPath('data.rest_time_minutes', 15)
        ->assertJsonPath('data.image_path', null)
        ->assertJsonCount(1, 'data.ingredients')
        ->assertJsonPath('data.ingredients.0.name', 'Farine')
        ->assertJsonCount(1, 'data.steps')
        ->assertJsonPath('data.steps.0.instruction', 'Mélanger.')
        ->assertJsonPath('data.tags.0.name', 'Dessert');

    $copy = Recipe::query()->where('public_id', $response->json('data.id'))->firstOrFail();
    expect($copy->user_id)->toBe($owner->id)
        ->and($copy->cookbook_id)->toBeNull()
        ->and($copy->author_id)->toBe($owner->id)
        ->and($copy->id)->not->toBe($source->id);
});

it('duplicates a recipe into a cookbook only with cookbook create permission', function (): void {
    $sourceOwner = User::factory()->create();
    $actor = User::factory()->create(['password' => 'password123']);
    $source = duplicateSourceRecipe($sourceOwner);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($actor, ['role' => 'editor']);

    $response = $this->withToken(duplicateRecipeToken($actor))
        ->postJson('/api/recipes/'.$source->public_id.'/duplicate', [
            'cookbook_id' => $cookbook->public_id,
        ]);

    $response->assertCreated();
    $copy = Recipe::query()->where('public_id', $response->json('data.id'))->firstOrFail();
    expect($copy->cookbook_id)->toBe($cookbook->id)->and($copy->user_id)->toBeNull();
    expect($copy->tags()->where('user_id', $actor->id)->count())->toBe(1);
});

it('rejects duplication when the target cookbook is not writable', function (): void {
    $owner = User::factory()->create();
    $actor = User::factory()->create(['password' => 'password123']);
    $source = duplicateSourceRecipe($owner);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($actor, ['role' => 'reader']);

    $this->withToken(duplicateRecipeToken($actor))
        ->postJson('/api/recipes/'.$source->public_id.'/duplicate', [
            'cookbook_id' => $cookbook->public_id,
        ])
        ->assertForbidden();

    expect(Recipe::query()->count())->toBe(1);
});

it('rejects duplication of an inaccessible cookbook recipe', function (): void {
    $owner = User::factory()->create();
    $actor = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $source = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $this->withToken(duplicateRecipeToken($actor))
        ->postJson('/api/recipes/'.$source->public_id.'/duplicate')
        ->assertForbidden();

    expect(Recipe::query()->count())->toBe(1);
});
