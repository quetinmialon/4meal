<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeNotificationToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('notifies a recipe creator about a new comment and a commenter about a reply', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $commenter = User::factory()->create(['password' => 'password123']);
    $replier = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($commenter, ['role' => 'commenter']);
    $cookbook->members()->attach($replier, ['role' => 'commenter']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $root = $this->withToken(recipeNotificationToken($commenter))
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => 'Très bonne recette !'])
        ->assertCreated();

    expect($owner->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($owner->fresh()->unreadNotifications->first()->data)->toMatchArray([
            'type' => 'recipe_comment',
            'recipe' => ['id' => $recipe->public_id, 'title' => $recipe->title],
            'sender' => ['id' => $commenter->id, 'name' => $commenter->name],
        ]);

    $this->withToken(recipeNotificationToken($replier))
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', [
            'content' => 'Je confirme !',
            'parent_id' => $root->json('data.id'),
        ])
        ->assertCreated();

    expect($commenter->fresh()->unreadNotifications)->toHaveCount(1)
        ->and($commenter->fresh()->unreadNotifications->first()->data['type'])->toBe('recipe_comment_reply')
        ->and($replier->fresh()->notifications)->toHaveCount(0);
});
