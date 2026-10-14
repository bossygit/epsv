<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'name' => 'Classe '.fake()->unique()->numberBetween(1, 900),
            'capacity' => 45,
            'is_active' => true,
        ];
    }
}
