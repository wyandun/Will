<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\ProcessCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Process>
 */
class ProcessFactory extends Factory
{
    protected $model = Process::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ProcessCategory::factory(),
            // 2-4 uppercase letters, matching StoreProcessRequest's regex.
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name_es' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'order_index' => 1,
        ];
    }
}
