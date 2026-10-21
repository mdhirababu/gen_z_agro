<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => \App\Models\User::factory(),
            'name' => fake()->company(),
            'phone' => fake()->numerify('01749911223'),
            'district' => 'Dhaka',
            'currency' => 'BDT',
        ];
    }
}
