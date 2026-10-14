<?php

namespace Tests\Unit;

use App\Services\AvailabilityCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Issue #5 の「完了の条件」を、番号の順に確かめる。
 * 表の最初の日は 2026-10-19（月）、今の日時はその日の 7:00 とする。
 */
class AvailabilityCalculatorTest extends TestCase
{
    private const FIRST_DATE = '2026-10-19';

    private const NOW = '2026-10-19 07:00:00';

    private const YESTERDAY = '2026-10-18 07:00:00';

    private const SEVEN_DAYS_AGO = '2026-10-12 07:00:00';

    private const EIGHT_DAYS_AGO = '2026-10-11 07:00:00';

    /** Issue #3 で決まった値（1時間・8:00〜22:00・7日）で、計算の部品を作る */
    private function calculator(int $slotMinutes = 60): AvailabilityCalculator
    {
        return new AvailabilityCalculator($slotMinutes, '08:00', '22:00', 7);
    }

    /** 表の中で、チームが 埋まり の枠を「日付 時刻」の形で並べて返す */
    private function busySlots(array $table): array
    {
        $busySlots = [];

        foreach ($table as $date => $slots) {
            foreach ($slots as $time => $slot) {
                if ($slot['team'] === 'busy') {
                    $busySlots[] = "{$date} {$time}";
                }
            }
        }

        return $busySlots;
    }

    /** 表の全部の枠で、チームの判定に出てくる値を重複なしで返す（全部が不明なら ['unknown']） */
    private function teamStatuses(array $table): array
    {
        $statuses = [];

        foreach ($table as $slots) {
            foreach ($slots as $slot) {
                $statuses[$slot['team']] = true;
            }
        }

        return array_keys($statuses);
    }

    /** その人が、表の全部の枠でどの判定に入っているかを重複なしで返す（全部が不明なら ['unknown']） */
    private function memberStatuses(array $table, int $id): array
    {
        $statuses = [];

        foreach ($table as $slots) {
            foreach ($slots as $slot) {
                foreach (['free', 'busy', 'unknown'] as $status) {
                    if (in_array($id, $slot[$status], true)) {
                        $statuses[$status] = true;
                    }
                }
            }
        }

        return array_keys($statuses);
    }

    /** 完了の条件 1：全員が昨日更新・予定は0件 → 全部の枠が 空き */
    public function test_everyone_is_free_when_there_are_no_schedules(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 3, 'schedule_updated_at' => self::YESTERDAY],
        ];

        $table = $this->calculator()->calculate($members, [], self::FIRST_DATE, self::NOW);

        // 7日ぶん（10/19〜10/25）
        $this->assertSame(
            ['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'],
            array_keys($table)
        );

        foreach ($table as $date => $slots) {
            // 1日は 8:00〜21:00 の14枠
            $this->assertCount(14, $slots, $date);
            $this->assertSame('08:00', array_key_first($slots));
            $this->assertSame('21:00', array_key_last($slots));

            foreach ($slots as $time => $slot) {
                $this->assertSame('free', $slot['team'], "{$date} {$time}");
                $this->assertSame([1, 2, 3], $slot['free'], "{$date} {$time}");
                $this->assertSame([], $slot['busy'], "{$date} {$time}");
                $this->assertSame([], $slot['unknown'], "{$date} {$time}");
            }
        }
    }

    /** 完了の条件 2：1人に「毎週月曜 9:00〜10:30」 → 月曜の 9:00・10:00 が 埋まり。11:00 は 空き。火曜は 空き */
    public function test_weekly_schedule_makes_the_slots_busy_on_that_weekday(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::YESTERDAY],
        ];
        $schedules = [
            ['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:30'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        // 月曜（10/19）の 9:00 と 10:00：1番の人が埋まり → チームも埋まり
        foreach (['09:00', '10:00'] as $time) {
            $this->assertSame('busy', $table['2026-10-19'][$time]['team'], $time);
            $this->assertSame([1], $table['2026-10-19'][$time]['busy'], $time);
            $this->assertSame([2], $table['2026-10-19'][$time]['free'], $time);
        }

        // 予定の前（8:00）と後（11:00）は 空き
        $this->assertSame('free', $table['2026-10-19']['08:00']['team']);
        $this->assertSame('free', $table['2026-10-19']['11:00']['team']);

        // 火曜（10/20）は、同じ時刻でも 空き
        $this->assertSame('free', $table['2026-10-20']['09:00']['team']);
        $this->assertSame([1, 2], $table['2026-10-20']['09:00']['free']);
    }

    /** 完了の条件 3：1人に「単発 10/21 13:00〜14:00」 → 10/21 の 13:00 だけ 埋まり。次の週の表には出ない */
    public function test_single_schedule_makes_the_slot_busy_only_on_that_date(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::YESTERDAY],
        ];
        $schedules = [
            ['user_id' => 1, 'type' => 'single', 'day_of_week' => null, 'date' => '2026-10-21', 'start_time' => '13:00:00', 'end_time' => '14:00:00'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        // 10/21（水）の 13:00：1番の人が埋まり → チームも埋まり
        $this->assertSame('busy', $table['2026-10-21']['13:00']['team']);
        $this->assertSame([1], $table['2026-10-21']['13:00']['busy']);
        $this->assertSame([2], $table['2026-10-21']['13:00']['free']);

        // この週の表で、埋まりの枠はその1つだけ（前後の時刻も、前後の日も 空き）
        $this->assertSame(['2026-10-21 13:00'], $this->busySlots($table));

        // 次の週の表（10/26〜11/1）には出ない
        $nextWeek = $this->calculator()->calculate($members, $schedules, '2026-10-26', self::NOW);
        $this->assertSame('2026-10-26', array_key_first($nextWeek));
        $this->assertSame([], $this->busySlots($nextWeek));

        // date が '2026-10-21 00:00:00' の形で来ても、同じ結果になる
        $schedules[0]['date'] = '2026-10-21 00:00:00';
        $this->assertSame($table, $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW));
    }

    /** 完了の条件 4：1人の更新が8日前 → その人は全部の枠で 不明。埋まりの人がいない枠は、チームも 不明 */
    public function test_member_is_unknown_when_the_last_update_is_older_than_the_limit(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::EIGHT_DAYS_AGO],
        ];
        // 2番の人（更新が8日前）に予定があっても、予定を見る前に 不明 と決まる
        $schedules = [
            ['user_id' => 2, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:00'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        // 2番の人は全部の枠で 不明、1番の人は全部の枠で 空き
        $this->assertSame(['unknown'], $this->memberStatuses($table, 2));
        $this->assertSame(['free'], $this->memberStatuses($table, 1));

        // 埋まりの人がいないので、チームは全部の枠で 不明
        $this->assertSame(['unknown'], $this->teamStatuses($table));

        // 予定のある月曜 9:00 も、埋まり ではなく 不明
        $this->assertSame([], $table['2026-10-19']['09:00']['busy']);
        $this->assertSame([2], $table['2026-10-19']['09:00']['unknown']);
    }

    /** 完了の条件 5：1人の schedule_updated_at が空（1回も登録していない）→ その人は全部の枠で 不明 */
    public function test_member_is_unknown_when_they_have_never_registered(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => null],
        ];

        $table = $this->calculator()->calculate($members, [], self::FIRST_DATE, self::NOW);

        $this->assertSame(['unknown'], $this->memberStatuses($table, 2));
        $this->assertSame(['free'], $this->memberStatuses($table, 1));
        $this->assertSame(['unknown'], $this->teamStatuses($table));
    }

    /** 完了の条件 6：同じ枠に、埋まりの人と不明の人がいる → チームは 埋まり */
    public function test_team_is_busy_when_a_busy_member_and_an_unknown_member_share_a_slot(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::EIGHT_DAYS_AGO],
        ];
        $schedules = [
            ['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:00'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        // 月曜 9:00：1番の人が埋まり、2番の人が不明 → チームは 埋まり（不明の人は unknown に入ったまま）
        $this->assertSame('busy', $table['2026-10-19']['09:00']['team']);
        $this->assertSame([1], $table['2026-10-19']['09:00']['busy']);
        $this->assertSame([2], $table['2026-10-19']['09:00']['unknown']);
        $this->assertSame([], $table['2026-10-19']['09:00']['free']);

        // 次の枠（10:00）は埋まりの人がいない → チームは 不明
        $this->assertSame('unknown', $table['2026-10-19']['10:00']['team']);
        $this->assertSame([1], $table['2026-10-19']['10:00']['free']);
        $this->assertSame([2], $table['2026-10-19']['10:00']['unknown']);
    }

    /** 完了の条件 7：刻みを30分にして、2番と同じ予定 → 月曜の 9:00・9:30・10:00 が 埋まり。10:30 は 空き */
    public function test_slots_follow_the_slot_minutes(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::YESTERDAY],
        ];
        $schedules = [
            ['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:30'],
        ];

        $table = $this->calculator(30)->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        // 1日は 8:00〜21:30 の28枠
        $this->assertCount(28, $table['2026-10-19']);
        $this->assertSame('08:00', array_key_first($table['2026-10-19']));
        $this->assertSame('21:30', array_key_last($table['2026-10-19']));

        // 埋まりは 9:00・9:30・10:00 の3つだけ
        $this->assertSame(['2026-10-19 09:00', '2026-10-19 09:30', '2026-10-19 10:00'], $this->busySlots($table));

        // 予定が終わる 10:30 の枠は 空き
        $this->assertSame('free', $table['2026-10-19']['10:30']['team']);
    }

    /** 完了の条件 8：表の時間帯からはみ出す予定（7:00〜8:30）→ 8:00 の枠だけ 埋まり */
    public function test_schedule_that_sticks_out_of_the_table_only_fills_the_slots_inside(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::YESTERDAY],
        ];
        $schedules = [
            // 前にはみ出す：月曜 7:00〜8:30
            ['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '07:00', 'end_time' => '08:30'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        $this->assertSame(['2026-10-19 08:00'], $this->busySlots($table));

        // 後ろにはみ出す：火曜 21:30〜23:00 → 21:00 の枠だけ 埋まり
        $schedules[] = ['user_id' => 2, 'type' => 'single', 'day_of_week' => null, 'date' => '2026-10-20', 'start_time' => '21:30', 'end_time' => '23:00'];
        $table = $this->calculator()->calculate($members, $schedules, self::FIRST_DATE, self::NOW);

        $this->assertSame(['2026-10-19 08:00', '2026-10-20 21:00'], $this->busySlots($table));
    }

    /** 完了の条件 9：1人の更新がちょうど7日前 → 不明にしない（7日を超えたら不明） */
    public function test_member_is_not_unknown_when_the_last_update_is_exactly_at_the_limit(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => self::YESTERDAY],
            ['id' => 2, 'schedule_updated_at' => self::SEVEN_DAYS_AGO],
        ];

        $table = $this->calculator()->calculate($members, [], self::FIRST_DATE, self::NOW);

        // ちょうど7日前（10/12 7:00）は、まだ 空き
        $this->assertSame(['free'], $this->memberStatuses($table, 2));
        $this->assertSame(['free'], $this->teamStatuses($table));

        // 1秒でも超えたら（10/12 6:59:59）不明
        $members[1]['schedule_updated_at'] = '2026-10-12 06:59:59';
        $table = $this->calculator()->calculate($members, [], self::FIRST_DATE, self::NOW);
        $this->assertSame(['unknown'], $this->memberStatuses($table, 2));
    }

    /** 表の最初の日は月曜でなくてよい：10/14（水）を渡す → 表は 10/14〜10/20。毎週月曜の予定は 10/19 に出る */
    public function test_first_date_does_not_have_to_be_a_monday(): void
    {
        $members = [
            ['id' => 1, 'schedule_updated_at' => '2026-10-13 07:00:00'],
            ['id' => 2, 'schedule_updated_at' => '2026-10-13 07:00:00'],
        ];
        $schedules = [
            ['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:30'],
        ];

        $table = $this->calculator()->calculate($members, $schedules, '2026-10-14', '2026-10-14 07:00:00');

        // 渡した日（水曜）から7日ぶん
        $this->assertSame(
            ['2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18', '2026-10-19', '2026-10-20'],
            array_keys($table)
        );

        // 月曜は6日目の 10/19。埋まりはその日の 9:00・10:00 だけ
        $this->assertSame(['2026-10-19 09:00', '2026-10-19 10:00'], $this->busySlots($table));
    }
}
