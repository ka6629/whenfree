<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * チームの1週間ぶんの 空き・埋まり・不明 を計算する。
 * データベースは読まない。メンバーと予定は配列で受け取る。
 */
class AvailabilityCalculator
{
    public const FREE = 'free';       // 空き

    public const BUSY = 'busy';       // 埋まり

    public const UNKNOWN = 'unknown'; // 不明

    /** 表に出す日数 */
    private const DAYS = 7;

    public function __construct(
        private int $slotMinutes, // 時間の刻み（分）
        private string $dayStart, // 表の最初の時刻（例 '08:00'）
        private string $dayEnd,   // 表の最後の時刻（例 '22:00'）
        private int $staleDays,   // この日数を超えて更新が無い人は「不明」
    ) {}

    /**
     * 表の最初の日から7日ぶんの表を作る。
     *
     * @param  array  $members  例 [['id' => 1, 'schedule_updated_at' => '2026-10-18 07:00:00'], ...]
     * @param  array  $schedules  例 [['user_id' => 1, 'type' => 'weekly', 'day_of_week' => 1, 'date' => null, 'start_time' => '09:00', 'end_time' => '10:30'], ...]
     * @param  string  $firstDate  表の最初の日（例 '2026-10-19'）
     * @param  string  $now  今の日時（例 '2026-10-19 07:00:00'）
     * @return array 日付 => 時刻 => ['team' => 判定, 'free' => [id, ...], 'busy' => [id, ...], 'unknown' => [id, ...]]
     */
    public function calculate(array $members, array $schedules, string $firstDate, string $now): array
    {
        $table = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            $date = (new DateTimeImmutable($firstDate))->modify("+{$day} days")->format('Y-m-d');

            foreach ($this->slotStarts() as $slotStart) {
                $people = [self::FREE => [], self::BUSY => [], self::UNKNOWN => []];

                foreach ($members as $member) {
                    $status = $this->memberStatus();
                    $people[$status][] = $member['id'];
                }

                $table[$date][$this->toTime($slotStart)] = [
                    'team' => $this->teamStatus(),
                    'free' => $people[self::FREE],
                    'busy' => $people[self::BUSY],
                    'unknown' => $people[self::UNKNOWN],
                ];
            }
        }

        return $table;
    }

    /** メンバー1人 × 枠1つの判定。今は全員「空き」（埋まり・不明は次の回から足す） */
    private function memberStatus(): string
    {
        return self::FREE;
    }

    /** チームとしての判定。今はいつも「空き」（埋まり・不明は次の回から足す） */
    private function teamStatus(): string
    {
        return self::FREE;
    }

    /** 1日ぶんの枠の始まりを「0時からの分」で返す（8:00〜22:00・1時間刻みなら 480, 540, ... 1260） */
    private function slotStarts(): array
    {
        $starts = [];
        $end = $this->toMinutes($this->dayEnd);

        for ($minutes = $this->toMinutes($this->dayStart); $minutes < $end; $minutes += $this->slotMinutes) {
            $starts[] = $minutes;
        }

        return $starts;
    }

    /** '09:30' や '09:30:00' を、0時からの分（570）に直す */
    private function toMinutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);

        return (int) $hour * 60 + (int) $minute;
    }

    /** 0時からの分（570）を '09:30' に直す */
    private function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
