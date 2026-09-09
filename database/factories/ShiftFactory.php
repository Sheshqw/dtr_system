<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name'                  => 'Regular Shift ' . fake()->unique()->numberBetween(1, 100000),
            'morning_in'            => '08:00:00',
            'morning_out'           => '12:00:00',
            'afternoon_in'          => '13:00:00',
            'afternoon_out'         => '17:00:00',
            'grace_period_minutes'  => 15,
            'required_hours'        => 8.00,
        ];
    }
}
