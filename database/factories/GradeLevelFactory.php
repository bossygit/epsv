<?php

namespace Database\Factories;

use App\Models\Cycle;
use App\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 900);

        return [
            'cycle_id' => Cycle::factory(),
            'name' => 'Niveau '.$n,
            'code' => 'NIV'.$n,
            'sequence' => 1,
        ];
    }
}
