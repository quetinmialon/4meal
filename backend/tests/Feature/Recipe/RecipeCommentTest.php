<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeCommentToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function commentableRecipe(User $owner, string $role = 'owner'): array
{
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => $role]);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create();

    return [$cookbook, $recipe];
}

it('allows commenter, editor and owner to list and create recipe comments', function (string $role): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $author = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $cookbook->members()->attach($author, ['role' => $role]);

    $this->withToken(recipeCommentToken($author))
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => 'Très bonne recette !'])
        ->assertCreated()
        ->assertJsonPath('data.content', 'Très bonne recette !')
        ->assertJsonPath('data.author.name', $author->name)
        ->assertJsonPath('data.author.role', $role);

    $this->withToken(recipeCommentToken($author))
        ->getJson('/api/recipes/'.$recipe->public_id.'/comments')
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Très bonne recette !')
        ->assertJsonPath('meta.pagination.per_page', 20);
})->with(['commenter', 'editor', 'owner']);

it('rejects readers, but allows comments on personal recipes', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $reader = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $cookbook->members()->attach($reader, ['role' => 'reader']);
    $personalRecipe = Recipe::factory()->create(['user_id' => $external->id]);

    foreach ([$reader, $external] as $user) {
        $token = recipeCommentToken($user);
        $this->withToken($token)
            ->getJson('/api/recipes/'.$recipe->public_id.'/comments')
            ->assertForbidden();
        $this->withToken($token)
            ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => 'Non autorisé'])
            ->assertForbidden();
    }

    $this->withToken(recipeCommentToken($external))
        ->getJson('/api/recipes/'.$personalRecipe->public_id.'/comments')
        ->assertOk();

    $this->withToken(recipeCommentToken($external))
        ->postJson('/api/recipes/'.$personalRecipe->public_id.'/comments', ['content' => 'Commentaire personnel'])
        ->assertCreated()
        ->assertJsonPath('data.author.role', null);
});

it('validates comment content and paginates comments newest first', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $token = recipeCommentToken($owner);

    $this->withToken($token)
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => '   '])
        ->assertUnprocessable();

    $this->withToken($token)
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => str_repeat('a', 2001)])
        ->assertUnprocessable();

    for ($i = 1; $i <= 21; $i++) {
        RecipeComment::query()->create([
            'recipe_id' => $recipe->id,
            'user_id' => $owner->id,
            'content' => 'Comment '.$i,
        ]);
    }

    $this->withToken($token)
        ->getJson('/api/recipes/'.$recipe->public_id.'/comments?per_page=20&page=1')
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Comment 21')
        ->assertJsonPath('data.19.content', 'Comment 2')
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 20)
        ->assertJsonPath('meta.pagination.has_more_pages', true);

    $this->withToken($token)
        ->getJson('/api/recipes/'.$recipe->public_id.'/comments?per_page=20&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Comment 1')
        ->assertJsonCount(1, 'data');
});

it('allows each commenting role to update and delete its own comment', function (string $role): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $author = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $cookbook->members()->attach($author, ['role' => $role]);
    $comment = RecipeComment::query()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $author->id,
        'content' => 'Avant modification',
    ]);

    $token = recipeCommentToken($author);
    $this->withToken($token)
        ->patchJson('/api/recipes/'.$recipe->public_id.'/comments/'.$comment->public_id, ['content' => 'Après modification'])
        ->assertOk()
        ->assertJsonPath('data.content', 'Après modification')
        ->assertJsonPath('data.author.role', $role)
        ->assertJsonPath('data.edited_at', fn ($value): bool => is_string($value));

    expect($comment->fresh()->edited_at)->not->toBeNull();

    $this->withToken($token)
        ->deleteJson('/api/recipes/'.$recipe->public_id.'/comments/'.$comment->public_id)
        ->assertNoContent();

    expect(RecipeComment::withTrashed()->find($comment->id)?->trashed())->toBeTrue();
})->with(['commenter', 'editor', 'owner']);

it('does not allow a reader or the cookbook owner to moderate another users comment', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $reader = User::factory()->create(['password' => 'password123']);
    $author = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $cookbook->members()->attach($reader, ['role' => 'reader']);
    $cookbook->members()->attach($author, ['role' => 'commenter']);
    $comment = RecipeComment::query()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $author->id,
        'content' => 'Commentaire original',
    ]);

    foreach ([$owner, $reader] as $user) {
        $token = recipeCommentToken($user);
        $this->withToken($token)
            ->patchJson('/api/recipes/'.$recipe->public_id.'/comments/'.$comment->public_id, ['content' => 'Intrusion'])
            ->assertForbidden();
        $this->withToken($token)
            ->deleteJson('/api/recipes/'.$recipe->public_id.'/comments/'.$comment->public_id)
            ->assertForbidden();
    }

    expect($comment->fresh()->content)->toBe('Commentaire original');
});

it('validates updated content and rejects a comment routed through another recipe', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    [$cookbook, $recipe] = commentableRecipe($owner);
    $otherRecipe = Recipe::factory()->create(['user_id' => $owner->id]);
    $comment = RecipeComment::query()->create([
        'recipe_id' => $recipe->id,
        'user_id' => $owner->id,
        'content' => 'Original',
    ]);
    $token = recipeCommentToken($owner);

    $this->withToken($token)
        ->patchJson('/api/recipes/'.$recipe->public_id.'/comments/'.$comment->public_id, ['content' => '   '])
        ->assertUnprocessable();

    $this->withToken($token)
        ->patchJson('/api/recipes/'.$otherRecipe->public_id.'/comments/'.$comment->public_id, ['content' => 'Autre recette'])
        ->assertNotFound();

    $this->withToken($token)
        ->deleteJson('/api/recipes/'.$otherRecipe->public_id.'/comments/'.$comment->public_id)
        ->assertNotFound();
});
