<?php

namespace Database\Factories;

use App\Models\ProcessCategory;
use App\Models\ProcessMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessCategory>
 */
class ProcessCategoryFactory extends Factory
{
    protected $model = ProcessCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'process_map_id' => ProcessMap::factory(),
            'type' => ProcessCategory::TYPE_STRATEGIC,
            'name_es' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'order_index' => 1,
        ];
    }

    /**
     * STRATEGIC division (fixed type, order 1).
     */
    public function strategic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProcessCategory::TYPE_STRATEGIC,
            'order_index' => 1,
        ]);
    }

    /**
     * VALUE CHAIN division (fixed type, order 2).
     */
    public function valueChain(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProcessCategory::TYPE_VALUE_CHAIN,
            'order_index' => 2,
        ]);
    }

    /**
     * SUPPORT division (fixed type, order 3).
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProcessCategory::TYPE_SUPPORT,
            'order_index' => 3,
        ]);
    }
}
