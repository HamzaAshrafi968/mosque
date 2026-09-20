<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'specialty' => fake()->randomElement(['قرآن كريم', 'تجويد', 'فقه', 'حديث', 'سيرة']),
            'hired_at' => fake()->dateTimeBetween('-3 years'),
            'is_active' => true,
        ];
    }
}
