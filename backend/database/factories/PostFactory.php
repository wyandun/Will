<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'franchise_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'type' => fake()->randomElement(['announcement', 'news', 'training', 'alert']),
            'visibility' => 'global',
            'is_pinned' => false,
            'file_path' => null,
            'file_type' => null,
            'file_name' => null,
            'image_url' => null,
            'file_url' => null,
            'scheduled_at' => null,
            'published_at' => null,
        ];
    }

    /**
     * Mark the post as pinned so it appears at the top of the feed.
     */
    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    /**
     * Scope the post to a specific franchise (visibility='franchise').
     */
    public function forFranchise(int $franchiseId): static
    {
        return $this->state([
            'visibility' => 'franchise',
            'franchise_id' => $franchiseId,
        ]);
    }

    /**
     * Schedule the post to be published in the future so it is hidden from the feed.
     */
    public function scheduledFuture(): static
    {
        return $this->state([
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Mark the post as already published (published_at in the past).
     */
    public function published(): static
    {
        return $this->state([
            'published_at' => now()->subHour(),
        ]);
    }
}
