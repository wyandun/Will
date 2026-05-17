<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => fake()->address(),
            'start_at' => $startAt,
            'end_at' => fake()->dateTimeBetween($startAt, (clone $startAt)->modify('+3 hours')),
            'all_day' => false,
            'timezone' => 'America/New_York',
            'color' => '#3B82F6',
            'visibility' => 'public',
            'type' => 'casual',
        ];
    }

    /**
     * Mark the event as all-day (times normalized to midnight).
     */
    public function allDay(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'all_day' => true,
                'start_at' => now()->addDay()->startOfDay(),
                'end_at' => now()->addDay()->startOfDay(),
            ];
        });
    }

    /**
     * Set a specific color for the event.
     */
    public function withColor(string $hex): static
    {
        return $this->state(['color' => $hex]);
    }
}
