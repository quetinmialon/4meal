<?php

namespace Database\Factories;

use App\Models\CookbookMessage;
use App\Models\CookbookMessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CookbookMessageReaction> */
class CookbookMessageReactionFactory extends Factory
{
    protected $model = CookbookMessageReaction::class;

    public function definition(): array
    {
        return [
            'cookbook_message_id' => CookbookMessage::factory(),
            'user_id' => User::factory(),
            'emoji' => fake()->randomElement(CookbookMessageReaction::ALLOWED_EMOJIS),
        ];
    }
}
