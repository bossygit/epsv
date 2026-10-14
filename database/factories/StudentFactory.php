<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'matricule' => sprintf('TEST-%s-%04d', now()->format('Y'), $sequence),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['M', 'F']),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'birth_place' => 'Brazzaville',
            'nationality' => 'Congolaise',
            'status' => Student::STATUS_ACTIVE,
        ];
    }
}
