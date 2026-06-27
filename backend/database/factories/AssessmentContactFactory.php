<?php

namespace Database\Factories;

use App\Models\AssessmentContact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentContact>
 */
class AssessmentContactFactory extends Factory
{
    protected $model = AssessmentContact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'sb_assessment_1',
            'status' => 'pending',
            'current_stage' => 'results',
            'company_name' => fake()->company(),
            'company_industry' => fake()->word(),
            'company_phone' => fake()->phoneNumber(),
            'company_email' => fake()->companyEmail(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'preferred_lang' => 'es',
            'score' => fake()->randomFloat(2, 0, 100),
            'token' => Str::random(60),
        ];
    }

    /**
     * State for a contact in 'reviewed' status.
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
        ]);
    }
}
