<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 years', '+1 year');
        $startYear = (int) $start->format('Y');

        return [
            'name' => $startYear.'-'.($startYear + 1),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->modify('+9 months')->format('Y-m-d'),
            'status' => AcademicYear::STATUS_DRAFT,
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'status' => AcademicYear::STATUS_ACTIVE,
            'is_current' => true,
        ]);
    }
}
