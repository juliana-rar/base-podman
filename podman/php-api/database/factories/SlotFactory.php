<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Slot>
 */
class SlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starts_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'notes' => fake()->optional()->sentence(3),
            'created_by' => User::factory()->admin(),
        ];
    }
}
