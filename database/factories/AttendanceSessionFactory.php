<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'session_date' => now()->subDay()->toDateString(),
            'slot' => AttendanceSession::SLOT_MORNING,
        ];
    }
}
