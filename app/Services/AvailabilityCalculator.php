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
        private int $checkDay,    // 週1回の確認の区切りの曜日（0＝日曜〜6＝土曜）
        private string $checkTime, // 区切りの時刻（例 '21:00'）。最後の区切りより前に更新した人は「不明」
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
                    $status = $this->memberStatus($member, $schedules, $date, $slotStart, $now);
                    $people[$status][] = $member['id'];
                }

                $table[$date][$this->toTime($slotStart)] = [
                    'team' => $this->teamStatus($people),
                    'free' => $people[self::FREE],
                    'busy' => $people[self::BUSY],
                    'unknown' => $people[self::UNKNOWN],
                ];
            }
        }

        return $table;
    }

    /** メンバー1人 × 枠1つの判定。上から順に見て、最初に当てはまった物で決める（不明 → 埋まり → 空き） */
    private function memberStatus(array $member, array $schedules, string $date, int $slotStart, string $now): string
    {
        if ($this->isUnknown($member, $now)) {
            return self::UNKNOWN; // 予定を見る前に決まる
        }

        $slotEnd = $slotStart + $this->slotMinutes;

        foreach ($schedules as $schedule) {
            if ((int) $schedule['user_id'] !== (int) $member['id']) {
                continue; // ほかの人の予定
            }

            if (! $this->isOnDate($schedule, $date)) {
                continue; // この日の予定ではない
            }

            if ($this->toMinutes($schedule['end_time']) <= $this->toMinutes($schedule['start_time'])) {
                continue; // 終了が開始以前の予定（日をまたぐ予定など）は、最初の版では判定に入れない
            }

            // 重なる ＝ 予定の開始 ＜ 枠の終わり かつ 予定の終わり ＞ 枠の始まり
            if ($this->toMinutes($schedule['start_time']) < $slotEnd && $this->toMinutes($schedule['end_time']) > $slotStart) {
                return self::BUSY;
            }
        }

        return self::FREE;
    }

    /**
     * 予定が今も合っているか分からない人か。1回も登録していない人と、最後の区切りより前に更新した人（#57）。
     * メンバーの一覧（TeamWeekService::members）でも同じ決まりを使うので public にしている。
     */
    public function isUnknown(array $member, string $now): bool
    {
        if (empty($member['schedule_updated_at'])) {
            return true; // 1回も登録していない
        }

        $updatedAt = new DateTimeImmutable($member['schedule_updated_at']);

        // 区切りちょうど（例 日曜 21:00:00）に更新した人は不明にしない。1秒でも前なら不明
        return $updatedAt < $this->lastCheckAt($now);
    }

    /** 今から見て一番新しい区切り。例：区切りが日曜 21:00 で、今が 10/20（火）なら 10/18（日）21:00 */
    private function lastCheckAt(string $now): DateTimeImmutable
    {
        $current = new DateTimeImmutable($now);
        [$hour, $minute] = explode(':', $this->checkTime);

        // 今日の区切りの時刻から、区切りの曜日まで日を戻す（0＝日曜〜6＝土曜）
        $daysBack = ((int) $current->format('w') - $this->checkDay + 7) % 7;
        $check = $current->setTime((int) $hour, (int) $minute)->modify("-{$daysBack} days");

        // 区切りの曜日で、まだ区切りの時刻の前（例 日曜 20:00）なら、1週間前の区切りになる
        return $check > $current ? $check->modify('-7 days') : $check;
    }

    /** その予定が、その日付に当たるか。毎週の予定は曜日で、単発の予定は日付で比べる */
    private function isOnDate(array $schedule, string $date): bool
    {
        if ($schedule['type'] === 'weekly') {
            // 0＝日曜〜6＝土曜
            return (int) $schedule['day_of_week'] === (int) (new DateTimeImmutable($date))->format('w');
        }

        if ($schedule['type'] === 'single') {
            // '2026-10-21' でも '2026-10-21 00:00:00' でも通るよう、先頭の10文字で比べる
            return substr((string) $schedule['date'], 0, 10) === $date;
        }

        return false;
    }

    /** チームとしての判定。埋まりが1人でも → 埋まり／埋まりがいなくて不明が1人でも → 不明／全員が空き → 空き */
    private function teamStatus(array $people): string
    {
        if ($people[self::BUSY] !== []) {
            return self::BUSY;
        }

        if ($people[self::UNKNOWN] !== []) {
            return self::UNKNOWN;
        }

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
