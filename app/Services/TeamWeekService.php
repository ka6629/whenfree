<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;

/**
 * 「チームの1週間」の入口（担当B）。
 * データベースからメンバーと予定を読み、AvailabilityCalculator に渡す。
 */
class TeamWeekService
{
    /**
     * チームの1週間ぶんの表を返す。
     *
     * @param  string|null  $firstDate  表の最初の日（例 '2026-10-19'）。null なら今日
     * @return array AvailabilityCalculator::calculate() と同じ形
     */
    public function forTeam(Team $team, ?string $firstDate = null): array
    {
        $members = $team->users()->orderBy('users.id')->get();

        // 日時は文字にしてから渡す（date の cast のまま toArray() すると UTC に直され、前の日になる）
        $memberRows = $members->map(fn (User $user) => [
            'id' => $user->id,
            'schedule_updated_at' => $user->schedule_updated_at?->format('Y-m-d H:i:s'),
        ])->all();

        $scheduleRows = Schedule::whereIn('user_id', $members->pluck('id'))->get()
            ->map(fn (Schedule $schedule) => [
                'user_id' => $schedule->user_id,
                'type' => $schedule->type,
                'day_of_week' => $schedule->day_of_week,
                'date' => $schedule->date?->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ])->all();

        return $this->calculator()->calculate(
            $memberRows,
            $scheduleRows,
            $firstDate ?? today()->format('Y-m-d'),
            now()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * メンバーごとの最終更新日と、「不明」かどうか（Issue #41）。
     *
     * @return array [['id' => 1, 'name' => '…', 'updated_at' => '2026/10/18 07:00' または null, 'unknown' => true/false], ...]
     */
    public function members(Team $team): array
    {
        $calculator = $this->calculator();
        $now = now()->format('Y-m-d H:i:s');

        return $team->users()->orderBy('users.id')->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'updated_at' => $user->schedule_updated_at?->format('Y/m/d H:i'),
                // 表と同じ決まりで判定する（7日を超えた人・1回も登録していない人は不明）
                'unknown' => $calculator->isUnknown(
                    ['schedule_updated_at' => $user->schedule_updated_at?->format('Y-m-d H:i:s')],
                    $now,
                ),
            ])->all();
    }

    /** 判定に使う値は config/whenfree.php から読む（Issue #3 で決めた値） */
    private function calculator(): AvailabilityCalculator
    {
        return new AvailabilityCalculator(
            config('whenfree.slot_minutes'),
            config('whenfree.day_start'),
            config('whenfree.day_end'),
            config('whenfree.stale_days'),
        );
    }
}
