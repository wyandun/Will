<?php

namespace Database\Factories;

use App\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Franchise>
 */
class FranchiseFactory extends Factory
{
    protected $model = Franchise::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => fake()->randomElement(['sm', 'sub']),
            'parent_company_id' => null,
            'owner_user_id' => null,
            'region' => fake()->state(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'country' => fake()->country(),
            'timezone' => fake()->timezone(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the franchise is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the franchise is an SM type.
     */
    public function sm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sm',
        ]);
    }

    /**
     * Indicate that the franchise is a sub-franchise type.
     */
    public function sub(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sub',
        ]);
    }
}
