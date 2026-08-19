<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\CookbookMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CookbookMember> */
class CookbookMemberFactory extends Factory
{
    protected $model = CookbookMember::class;

    public function definition(): array
    {
        return ['cookbook_id' => Cookbook::factory(), 'user_id' => User::factory(), 'role' => $this->faker->randomElement(['editor', 'reader', 'commenter']), 'joined_at' => now()->subDays($this->faker->numberBetween(1, 90))];
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner']);
    }

    public function editor(): static
    {
        return $this->state(['role' => 'editor']);
    }

    public function reader(): static
    {
        return $this->state(['role' => 'reader']);
    }

    public function commenter(): static
    {
        return $this->state(['role' => 'commenter']);
    }
}
