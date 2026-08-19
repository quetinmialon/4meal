<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CookbookInvitation> */
class CookbookInvitationFactory extends Factory
{
    protected $model = CookbookInvitation::class;

    public function definition(): array
    {
        $token = $this->faker->unique()->sha256();

        return ['cookbook_id' => Cookbook::factory(), 'invited_by' => User::factory(), 'email' => $this->faker->unique()->safeEmail(), 'token_hash' => hash('sha256', $token), 'role' => $this->faker->randomElement(['editor', 'reader', 'commenter']), 'expires_at' => now()->addDays(7), 'accepted_at' => null, 'accepted_by' => null, 'declined_at' => null, 'declined_by' => null];
    }

    public function pending(): static
    {
        return $this->state(['accepted_at' => null, 'accepted_by' => null, 'declined_at' => null, 'declined_by' => null, 'expires_at' => now()->addDays(7)]);
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(['accepted_at' => now()->subDay(), 'accepted_by' => $user?->getKey() ?? User::factory(), 'declined_at' => null, 'declined_by' => null]);
    }

    public function refused(?User $user = null): static
    {
        return $this->state(['declined_at' => now()->subDay(), 'declined_by' => $user?->getKey() ?? User::factory(), 'accepted_at' => null, 'accepted_by' => null]);
    }
}
