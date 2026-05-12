<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostInteraction>
 */
class PostInteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'type' => 'like',
            'content' => null,
        ];
    }

    /**
     * Create a comment interaction (requires content).
     */
    public function comment(): static
    {
        return $this->state([
            'type' => 'comment',
            'content' => fake()->sentence(),
        ]);
    }
}
