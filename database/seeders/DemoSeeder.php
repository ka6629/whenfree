<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::create(['name' => 'デモチーム', 'invite_code' => 'demo01']);

        // 予定を更新したばかりの人
        $sato = User::factory()->create([
            'name' => '佐藤', 'email' => 'sato@example.com', 'schedule_updated_at' => now(),
        ]);
        foreach ([1, 3, 5] as $day) {
            $sato->schedules()->create(['type' => 'weekly', 'day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '12:00', 'title' => '授業']);
        }
        foreach ([2, 4] as $day) {
            $sato->schedules()->create(['type' => 'weekly', 'day_of_week' => $day, 'start_time' => '18:00', 'end_time' => '22:00', 'title' => 'バイト']);
        }

        // 2日前に更新した人（単発の予定あり）
        $suzuki = User::factory()->create([
            'name' => '鈴木', 'email' => 'suzuki@example.com', 'schedule_updated_at' => now()->subDays(2),
        ]);
        foreach ([1, 2, 3, 4, 5] as $day) {
            $suzuki->schedules()->create(['type' => 'weekly', 'day_of_week' => $day, 'start_time' => '13:00', 'end_time' => '16:00', 'title' => '授業']);
        }
        $suzuki->schedules()->create(['type' => 'single', 'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '12:00', 'title' => '歯医者']);

        // 10日前に更新した人（不明になる）
        $takahashi = User::factory()->create([
            'name' => '高橋', 'email' => 'takahashi@example.com', 'schedule_updated_at' => now()->subDays(10),
        ]);
        $takahashi->schedules()->create(['type' => 'weekly', 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '10:30', 'title' => '授業']);

        // 1回も予定を登録していない人（不明になる）
        $tanaka = User::factory()->create([
            'name' => '田中', 'email' => 'tanaka@example.com', 'schedule_updated_at' => null,
        ]);

        $team->users()->attach([$sato->id, $suzuki->id, $takahashi->id, $tanaka->id]);
    }
}