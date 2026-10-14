<?php

namespace Database\Factories;

use App\Models\Cycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Primaire',
            'code' => 'PRIM',
            'sequence' => 1,
        ];
    }
}
