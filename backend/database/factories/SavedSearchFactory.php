<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavedSearch> */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => $this->faker->randomElement(['Dîners rapides', 'Recettes végétariennes', 'Desserts du dimanche', 'Batch cooking']).' '.$this->faker->unique()->numberBetween(1, 999999), 'criteria' => ['query' => null, 'tags' => [], 'difficulty' => null, 'max_prep_time_minutes' => null]];
    }

    public function vegetarian(): static
    {
        return $this->state(['criteria' => ['query' => null, 'tags' => ['vegetarien'], 'difficulty' => null, 'max_prep_time_minutes' => 45]]);
    }
}
