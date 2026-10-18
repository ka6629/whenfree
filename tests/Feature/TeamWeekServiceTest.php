<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\AvailabilityCalculator;
use App\Services\TeamWeekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 「チームの1週間」の入口（DB から読んで判定の処理に渡す）を確かめる。
 */
class TeamWeekServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00'); // 「今」を 2026-10-19（月）7:00 に固定する
    }

    /** 単発の予定は、DB に入れた日付の枠で「埋まり」になる（前の日にずれない） */
    public function test_single_schedule_is_busy_on_its_own_date(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $members[0]->schedules()->create([
            'title' => 'ゼミ',
            'type' => 'single',
            'date' => '2026-10-21',
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);

        $week = (new TeamWeekService)->forTeam($team);

        // 表の最初の日を渡さない時は、今日から7日間
        $this->assertSame(
            ['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'],
            array_keys($week),
        );
        $this->assertSame(AvailabilityCalculator::BUSY, $week['2026-10-21']['13:00']['team']);
        $this->assertSame([$members[0]->id], $week['2026-10-21']['13:00']['busy']);
        $this->assertSame(AvailabilityCalculator::FREE, $week['2026-10-20']['13:00']['team']);
    }

    /** ほかのチームの人の予定は入らない */
    public function test_schedules_of_other_teams_are_not_included(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        [, $otherMembers] = $this->teamWithMembers(1);
        $otherMembers[0]->schedules()->create([
            'type' => 'weekly',
            'day_of_week' => 1, // 月曜
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $week = (new TeamWeekService)->forTeam($team, '2026-10-19');

        $this->assertSame(AvailabilityCalculator::FREE, $week['2026-10-19']['09:00']['team']);
        $this->assertSame([$members[0]->id, $members[1]->id], $week['2026-10-19']['09:00']['free']);
    }

    /**
     * Team の Factory はまだ無いので、Team::create() で作ってメンバーをつなぐ。
     * メンバーは「今」予定を更新した人にする（不明にならないように）。
     *
     * @return array{0: Team, 1: array<int, User>}
     */
    private function teamWithMembers(int $count): array
    {
        $team = Team::create(['name' => 'テストチーム', 'invite_code' => Str::random(16)]);

        $members = User::factory()->count($count)->create()->all();
        foreach ($members as $member) {
            $member->forceFill(['schedule_updated_at' => now()])->save();
            $team->users()->attach($member);
        }

        return [$team, $members];
    }
}
