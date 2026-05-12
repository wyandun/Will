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
    protected $model = Event::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'location' => $this->faker->city(),
            'start_at' => $start,
            'end_at' => $end,
            'timezone' => 'America/New_York',
            'all_day' => false,
            'color' => '#3B82F6',
            'visibility' => 'private',
            'type' => 'meeting',
        ];
    }

    public function allDay(): static
    {
        return $this->state(fn () => [
            'all_day' => true,
            'start_at' => $this->faker->date(),
            'end_at' => $this->faker->date(),
        ]);
    }

    public function public(): static
    {
        return $this->state(fn () => [
            'visibility' => 'public',
        ]);
    }

    public function franchise(): static
    {
        return $this->state(fn () => [
            'visibility' => 'franchise',
        ]);
    }
}
