<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Google カレンダーから読み込んだ「埋まり」の時間（Issue #54）。予定の中身は持たない
#[Fillable(['user_id', 'date', 'start_time', 'end_time'])]
class GoogleBusyTime extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * AvailabilityCalculator にそのまま渡せる形（単発の予定と同じ形）で返す
     *
     * @return array [['user_id' => 1, 'type' => 'single', 'day_of_week' => null, 'date' => '2026-10-19', 'start_time' => '10:00', 'end_time' => '11:00'], ...]
     */
    public static function rowsFor(iterable $userIds): array
    {
        return static::whereIn('user_id', collect($userIds))->get()
            ->map(fn (self $busy) => [
                'user_id' => $busy->user_id,
                'type' => 'single',
                'day_of_week' => null,
                'date' => $busy->date->format('Y-m-d'),
                'start_time' => $busy->start_time,
                'end_time' => $busy->end_time,
            ])->all();
    }

    /** その人の読み込んだ時間を、新しく読み込んだものに入れ替える */
    public static function replaceFor(int $userId, array $rows): void
    {
        DB::transaction(function () use ($userId, $rows) {
            static::deleteFor($userId);
            static::insert($rows);
        });
    }

    /** その人の読み込んだ時間を消す */
    public static function deleteFor(int $userId): void
    {
        static::where('user_id', $userId)->delete();
    }
}
