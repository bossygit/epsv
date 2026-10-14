<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolPeriod>
 */
class SchoolPeriodFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => '1er Trimestre',
            'sequence' => 1,
            'type' => 'trimestre',
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->modify('+3 months')->format('Y-m-d'),
            'weight' => 1,
            'status' => SchoolPeriod::STATUS_OPEN,
        ];
    }
}
