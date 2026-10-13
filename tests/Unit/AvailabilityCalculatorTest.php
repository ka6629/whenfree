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
}
