<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(8, 20); // 8:00〜20:00 に始まる1〜2時間の候補

        return [
            // TeamFactory はまだ無いので、teams に直接1行入れる
            'team_id' => fn () => DB::table('teams')->insertGetId([
                'name' => fake()->word(),
                'invite_code' => Str::random(16),
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'created_by' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $start),
            'end_time' => sprintf('%02d:00', $start + fake()->numberBetween(1, 2)),
            'title' => null,
            'needed_count' => null,
            'status' => Candidate::OPEN,
        ];
    }

    /**
     * 確定した候補。
     */
    public function decided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Candidate::DECIDED,
        ]);
    }

    /**
     * 取り消した候補。
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Candidate::CANCELED,
        ]);
    }
}
