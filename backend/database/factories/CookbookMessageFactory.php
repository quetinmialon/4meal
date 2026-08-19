<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CookbookMessage> */
class CookbookMessageFactory extends Factory
{
    protected $model = CookbookMessage::class;

    public function definition(): array
    {
        return ['cookbook_id' => Cookbook::factory(), 'user_id' => User::factory(), 'content' => $this->faker->randomElement(['La recette est prête pour dimanche !', 'On peut remplacer la crème par du lait de coco.', 'Qui apporte le dessert ?']), 'edited_at' => null, 'deleted_at' => null, 'deleted_by_user_id' => null];
    }

    public function edited(): static
    {
        return $this->state(['edited_at' => now()->subMinutes(10)]);
    }

    public function deleted(?User $user = null): static
    {
        return $this->state(['deleted_at' => now()->subHour(), 'deleted_by_user_id' => $user?->getKey() ?? User::factory()]);
    }
}
