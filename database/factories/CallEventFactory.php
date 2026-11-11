<?php

namespace Database\Factories;

use App\Enums\CallEventType;
use App\Models\CallEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallEvent>
 */
class CallEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CallEventType::Incoming,
            'call_uuid' => fake()->uuid(),
            'from' => fake()->numerify('447#########'),
            'to' => fake()->numerify('4479########'),
            'status' => null,
            'message' => 'Incoming call',
        ];
    }
}
