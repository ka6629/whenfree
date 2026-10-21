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

    /** 毎週の予定は、DB の day_of_week（0＝日曜〜6＝土曜）の曜日の枠で「埋まり」になる */
    public function test_weekly_schedule_is_busy_on_its_day_of_week(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $members[0]->schedules()->create([
            'title' => 'バイト',
            'type' => 'weekly',
            'day_of_week' => 3, // 水曜
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        $members[1]->schedules()->create([
            'title' => '部活',
            'type' => 'weekly',
            'day_of_week' => 0, // 日曜
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $week = (new TeamWeekService)->forTeam($team, '2026-10-19');

        $this->assertSame([$members[0]->id], $week['2026-10-21']['10:00']['busy']); // 10/21 は水曜
        $this->assertSame([$members[1]->id], $week['2026-10-25']['09:00']['busy']); // 10/25 は日曜

        // 1週間で埋まりの枠は、この2つだけ（ほかの曜日には入らない）
        $busy = [];
        foreach ($week as $date => $slots) {
            foreach ($slots as $time => $slot) {
                if ($slot['team'] === AvailabilityCalculator::BUSY) {
                    $busy[] = "{$date} {$time}";
                }
            }
        }
        $this->assertSame(['2026-10-21 10:00', '2026-10-25 09:00'], $busy);
    }

    /** 予定を1回も登録していない人と、最後の区切り（10/18（日）21:00）より前に更新した人は「不明」。区切りより後の人は「空き」 */
    public function test_members_without_recent_update_are_unknown(): void
    {
        [$team, $members] = $this->teamWithMembers(3);
        $members[0]->forceFill(['schedule_updated_at' => null])->save();
        $members[1]->forceFill(['schedule_updated_at' => '2026-10-18 20:00:00'])->save(); // 昨日でも、区切りより前
        $members[2]->forceFill(['schedule_updated_at' => '2026-10-18 22:00:00'])->save(); // 区切りより後

        $week = (new TeamWeekService)->forTeam($team, '2026-10-19');

        $slot = $week['2026-10-20']['13:00'];
        $this->assertSame(AvailabilityCalculator::UNKNOWN, $slot['team']);
        $this->assertSame([$members[0]->id, $members[1]->id], $slot['unknown']);
        $this->assertSame([$members[2]->id], $slot['free']);
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
