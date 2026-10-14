<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'student_id' => Student::factory(),
            'value' => fake()->randomFloat(2, 5, 19),
            'status' => Grade::STATUS_ENTERED,
            'entered_at' => now(),
        ];
    }

    public function missing(): static
    {
        return $this->state(fn () => ['value' => null, 'status' => Grade::STATUS_MISSING]);
    }
}
