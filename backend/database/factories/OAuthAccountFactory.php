<?php

namespace Database\Factories;

use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OAuthAccount> */
class OAuthAccountFactory extends Factory
{
    protected $model = OAuthAccount::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'provider' => $this->faker->randomElement(['google', 'microsoft']), 'provider_user_id' => $this->faker->unique()->uuid(), 'provider_email' => $this->faker->unique()->safeEmail(), 'access_token' => $this->faker->sha256(), 'refresh_token' => $this->faker->sha256(), 'token_expires_at' => now()->addHour()];
    }
}
