<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id'            => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name'             => fake()->firstName(),
            'last_name'              => fake()->lastName(),
            'email'                  => fake()->unique()->safeEmail(),
            'password'               => static::$password ??= Hash::make('password'),
            'role'                   => 'employee',
            'employment_status'      => 'permanent',
            'is_active'              => true,
            'failed_login_attempts'  => 0,
            'locked_until'           => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => 'super_admin', 'campus_id' => null]);
    }

    public function hrOfficer(): static
    {
        return $this->state(fn () => ['role' => 'hr_officer']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
