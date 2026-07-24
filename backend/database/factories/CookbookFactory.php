<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Cookbook> */
class CookbookFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->sentence(2);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
