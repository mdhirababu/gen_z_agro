<?php

namespace Database\Factories;

use App\Models\Cow;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cow>
 */
class CowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */


    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'tag_number' => 'T' . fake()->unique()->numerify('####'),
            'name' => fake()->firstName(),
            'category' => 'milking',
            'breed' => 'deshi',
            'sex' => 'female',
            'birth_date' => now()->subYears(3)->toDateString(),
            'acquisition_type' => 'purchased',
            'purchase_price' => 80000,
            'status' => 'active',
            'source' => 'manual',
        ];
    }
}
