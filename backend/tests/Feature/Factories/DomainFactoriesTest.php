<?php

use App\Enums\NotificationChannel;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\CookbookMember;
use App\Models\CookbookMessage;
use App\Models\NotificationPreference;
use App\Models\OAuthAccount;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\RecipeComment;
use App\Models\RecipeCommentReaction;
use App\Models\RecipeIngredient;
use App\Models\RecipeRating;
use App\Models\RecipeStep;
use App\Models\SavedSearch;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a coherent graph through the domain factories', function (): void {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $member = User::factory()->create(['email' => 'member@example.test']);
    $cookbook = Cookbook::factory()->withOwner($owner)->create();
    CookbookMember::factory()->owner()->create(['cookbook_id' => $cookbook->id, 'user_id' => $owner->id]);
    CookbookMember::factory()->editor()->create(['cookbook_id' => $cookbook->id, 'user_id' => $member->id]);

    $recipe = Recipe::factory()->inCookbook($cookbook)->published()->create(['author_id' => $owner->id]);
    RecipeIngredient::factory()->atPosition(1)->create(['recipe_id' => $recipe->id]);
    RecipeIngredient::factory()->atPosition(2)->create(['recipe_id' => $recipe->id]);
    RecipeStep::factory()->atPosition(1)->create(['recipe_id' => $recipe->id]);
    $comment = RecipeComment::factory()->create(['recipe_id' => $recipe->id, 'user_id' => $member->id]);
    RecipeComment::factory()->reply($comment)->create(['recipe_id' => $recipe->id, 'user_id' => $owner->id]);

    $tag = Tag::factory()->create(['user_id' => $owner->id]);
    $recipe->tags()->attach($tag);
    $recipe->favoritedBy()->attach($member);
    RecipeRating::factory()->create(['recipe_id' => $recipe->id, 'user_id' => $member->id]);
    RecipeCommentReaction::factory()->create(['recipe_comment_id' => $comment->id, 'user_id' => $owner->id]);
    CookbookMessage::factory()->create(['cookbook_id' => $cookbook->id, 'user_id' => $member->id]);
    CookbookInvitation::factory()->pending()->create(['cookbook_id' => $cookbook->id, 'invited_by' => $owner->id]);
    PlannedMeal::factory()->inCookbook($cookbook)->create(['recipe_id' => $recipe->id]);
    OAuthAccount::factory()->create(['user_id' => $owner->id, 'provider' => 'google']);
    NotificationPreference::factory()->create(['user_id' => $owner->id, 'channel' => NotificationChannel::Web]);
    SavedSearch::factory()->vegetarian()->create(['user_id' => $owner->id]);
    RecipeAudit::factory()->updated()->create(['recipe_id' => $recipe->id, 'actor_id' => $owner->id]);

    expect($recipe->fresh()->ingredients)->toHaveCount(2)
        ->and($recipe->tags)->toHaveCount(1)
        ->and($recipe->comments)->toHaveCount(2)
        ->and($cookbook->members)->toHaveCount(2);
});
