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

    /** Issue #3 で決まった値（1時間・8:00〜22:00・7日）で、計算の部品を作る */
    private function calculator(int $slotMinutes = 60): AvailabilityCalculator
    {
        return new AvailabilityCalculator($slotMinutes, '08:00', '22:00', 7);
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
}
