<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserMedication>
 */
class UserMedicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'rxcui' => $this->faker->numerify('######'),
            'drug_name' => $this->faker->words(3, true) . ' ' . $this->faker->randomNumber(2) . ' MG Oral Tablet',
            'base_names' => [$this->faker->word(), $this->faker->word()],
            'dose_form_group_names' => ['Oral Tablet', 'Capsule'],
        ];
    }
}
