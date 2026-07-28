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
        $name = $this->faker->sentence(2);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'description' => $this->faker->optional()->paragraph(),
        ];
    }
}
