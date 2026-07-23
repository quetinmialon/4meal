<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('supports personal and cookbook recipe ownership', function (): void {
    $user = User::factory()->create();
    $cookbook = Cookbook::factory()->create();

    $personal = Recipe::factory()->create(['user_id' => $user->id]);
    $shared = Recipe::factory()->inCookbook($cookbook)->create();

    expect($user->fresh()->recipes)->toHaveCount(1)
        ->and($personal->fresh()->user->is($user))->toBeTrue()
        ->and($shared->fresh()->cookbook->is($cookbook))->toBeTrue();
});

it('relates ingredients and steps to a recipe in position order', function (): void {
    $recipe = Recipe::factory()->create();

    RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'position' => 2]);
    RecipeIngredient::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
    RecipeStep::factory()->create(['recipe_id' => $recipe->id, 'position' => 2]);
    RecipeStep::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);

    expect($recipe->fresh()->ingredients->pluck('position')->all())->toBe([1, 2])
        ->and($recipe->fresh()->steps->pluck('position')->all())->toBe([1, 2])
        ->and($recipe->ingredients()->first()->recipe->is($recipe))->toBeTrue();
});

it('relates recipes and user tags through the pivot', function (): void {
    $recipe = Recipe::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $recipe->user_id]);

    $recipe->tags()->attach($tag);

    expect($recipe->fresh()->tags->first()->is($tag))->toBeTrue()
        ->and($tag->fresh()->recipes->first()->is($recipe))->toBeTrue()
        ->and($recipe->user->tags->first()->is($tag))->toBeTrue();
});

it('enforces one recipe owner at the database level', function (): void {
    expect(fn () => Recipe::query()->create([
        'title' => 'Invalid',
        'user_id' => User::factory()->create()->id,
        'cookbook_id' => Cookbook::factory()->create()->id,
    ]))->toThrow(InvalidArgumentException::class);
});
