<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Response>
 */
class ResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'user_id' => User::factory(),
            'answer' => fake()->randomElement([Response::YES, Response::NO]),
        ];
    }

    /**
     * 「はい」の返事。
     */
    public function yes(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer' => Response::YES,
        ]);
    }

    /**
     * 「いいえ」の返事。
     */
    public function no(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer' => Response::NO,
        ]);
    }
}
