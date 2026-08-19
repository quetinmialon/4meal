<?php

namespace Database\Factories;

use App\Models\RecipeComment;
use App\Models\RecipeCommentReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeCommentReaction> */
class RecipeCommentReactionFactory extends Factory
{
    protected $model = RecipeCommentReaction::class;

    public function definition(): array
    {
        return ['recipe_comment_id' => RecipeComment::factory(), 'user_id' => User::factory(), 'emoji' => $this->faker->randomElement(RecipeCommentReaction::ALLOWED_EMOJIS)];
    }
}
