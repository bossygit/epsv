<?php

namespace Database\Factories;

use App\Models\ClassSubject;
use App\Models\Evaluation;
use App\Models\SchoolPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_subject_id' => ClassSubject::factory(),
            'school_period_id' => SchoolPeriod::factory(),
            'name' => 'Devoir n°1',
            'type' => 'devoir',
            'evaluation_date' => now()->subWeeks(2)->toDateString(),
            'max_score' => 20,
            'coefficient' => 1,
            'status' => Evaluation::STATUS_PUBLISHED,
            'published_at' => now()->subWeeks(2),
        ];
    }
}
