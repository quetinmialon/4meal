<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function deleteRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('allows the personal owner to delete a recipe and removes its linked data', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $recipe->ingredients()->create(['position' => 1, 'name' => 'Farine']);
    $recipe->steps()->create(['position' => 1, 'instruction' => 'Mélanger.']);
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $recipe->tags()->attach($tag);

    $this->withToken(deleteRecipeToken($user))
        ->deleteJson('/api/recipes/'.$recipe->public_id)
        ->assertNoContent();

    $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
    $this->assertDatabaseMissing('recipe_steps', ['recipe_id' => $recipe->id]);
    $this->assertDatabaseMissing('recipe_tag', ['recipe_id' => $recipe->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

it('denies a personal recipe deletion to another user', function (): void {
    $owner = User::factory()->create();
    $external = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'author_id' => $owner->id]);

    $this->withToken(deleteRecipeToken($external))
        ->deleteJson('/api/recipes/'.$recipe->public_id)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');

    $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'deleted_at' => null]);
});

it('allows only the cookbook owner to delete a cookbook recipe', function (string $role, bool $allowed): void {
    $owner = User::factory()->create();
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => $role]);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $response = $this->withToken(deleteRecipeToken($member))
        ->deleteJson('/api/recipes/'.$recipe->public_id);

    if ($allowed) {
        $response->assertNoContent();
        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    } else {
        $response->assertForbidden();
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'deleted_at' => null]);
    }
})->with([
    ['owner', true],
    ['editor', false],
    ['reader', false],
]);
