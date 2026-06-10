<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Franchise;
use App\Models\ProcessMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessMap>
 */
class ProcessMapFactory extends Factory
{
    protected $model = ProcessMap::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // No CompanyFactory exists yet; build a company (with its franchise)
            // inline so a ProcessMap can be created standalone in tests.
            'company_id' => fn () => Company::create([
                'name' => fake()->company(),
                'sm_franchise_id' => Franchise::factory()->create()->id,
            ])->id,
            'type' => 'custom',
            'name_es' => fake()->words(3, true),
            'name_en' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * The auto-seeded "franquiciadora" map (SM operations view).
     */
    public function franquiciadora(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'franquiciadora']);
    }

    /**
     * The auto-seeded "franquiciada" map (SB / sub-franchise view).
     */
    public function franquiciada(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'franquiciada']);
    }

    /**
     * Mark the map as hidden (soft toggle, not deleted).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Attach the map to an existing company instead of creating one.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->id]);
    }
}
