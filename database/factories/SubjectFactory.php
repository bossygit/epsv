<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 900);

        return [
            'name' => 'Matière '.$n,
            'code' => 'MAT'.$n,
            'is_active' => true,
        ];
    }
}
