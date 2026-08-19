<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationPreference> */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'type' => $this->faker->randomElement(NotificationType::current()), 'channel' => $this->faker->randomElement(NotificationChannel::cases())];
    }

    public function email(): static
    {
        return $this->state(['channel' => NotificationChannel::Mail]);
    }

    public function disabled(): static
    {
        return $this->state(['channel' => NotificationChannel::None]);
    }
}
