<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\TeamWeekService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeamWeekController extends Controller
{
    // チームの1週間（メンバーだけ・今日から7日間）
    public function show(Request $request, Team $team, TeamWeekService $service)
    {
        abort_unless($team->users()->whereKey($request->user()->id)->exists(), 403);

        $memberCount = $team->users()->count();
        $week = $memberCount > 0 ? $service->forTeam($team) : [];
        $members = $memberCount > 0 ? $service->members($team) : [];
        $checkDay = config('whenfree.check_day');   // 週1回の確認の区切り（#57）。画面の説明に使う
        $checkTime = config('whenfree.check_time');
        $cells = $this->cells($week, $members);
        $endTimes = $this->endTimes($week);

        return view('teams.week', compact('team', 'memberCount', 'week', 'members', 'checkDay', 'checkTime', 'cells', 'endTimes'));
    }
        /**
     * 表のマスごとに、印・人数・名前をまとめる（#56）。
     * 判定の処理（AvailabilityCalculator）は変えず、人数から印を決める。
     * ○ 全員が空き／× 全員に予定あり／△ 予定がある人がいて全員ではない／？ 予定がある人はいなくて不明の人がいる
     */
    private function cells(array $week, array $members): array
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $total = count($members);
        $byId = collect($members)->keyBy('id');
        $now = now();
        $cells = [];

        foreach ($week as $date => $times) {
            $day = Carbon::parse($date);
            $dateLabel = $day->format('n/j').'（'.$weekdays[$day->dayOfWeek].'）';

            foreach ($times as $time => $slot) {
                $free = count($slot['free']);
                $busy = count($slot['busy']);
                $unknown = count($slot['unknown']);

                $mark = match (true) {
                    $busy === $total => '×',
                    $busy > 0 => '△',
                    $unknown > 0 => '？',
                    default => '○',
                };

                $cells[$date][$time] = [
                    'mark' => $mark,
                    // △ と ？ だけ人数を出す
                    'text' => in_array($mark, ['△', '？'], true) ? "{$mark}{$free}/{$total}" : $mark,
                    // マウスを乗せた時・読み上げの時の言葉
                    'label' => match ($mark) {
                        '○' => '全員空き',
                        '×' => '全員予定あり',
                        '△' => "一部空き {$free}/{$total}人",
                        '？' => "不明の人がいる {$free}/{$total}人",
                    },
                    'class' => $this->cellClass($mark, $free, $total),
                    // 枠の始まりが過ぎていたら押せない
                    'past' => Carbon::parse("{$date} {$time}")->lt($now),
                    'date' => $date,
                    'time' => $time,
                    'dateLabel' => $dateLabel,
                    // 小窓に出す名前（予定の中身は出さない）
                    'free' => array_map(fn ($id) => $byId[$id]['name'], $slot['free']),
                    'busy' => array_map(fn ($id) => $byId[$id]['name'], $slot['busy']),
                    'unknown' => array_map(
                        fn ($id) => $byId[$id]['name'].'（最終更新 '.($byId[$id]['updated_at'] ?? 'まだ登録していません').'）',
                        $slot['unknown'],
                    ),
                ];
            }
        }

        return $cells;
    }

    /** マスの色。○ がいちばん濃い緑で、△ は空いている人が多いほど濃くする */
    private function cellClass(string $mark, int $free, int $total): string
    {
        return match ($mark) {
            '○' => 'bg-green-300 text-green-950',
            '×' => 'bg-red-50 text-red-700',
            '？' => 'bg-gray-100 text-gray-700',
            default => match (true) {
                $free * 3 >= $total * 2 => 'bg-green-200 text-green-950',
                $free * 3 >= $total => 'bg-green-100 text-green-900',
                $free > 0 => 'bg-green-50 text-green-900',
                default => 'bg-white text-gray-700',
            },
        };
    }

    /** 小窓で選べる終了の時刻（例 ['09:00', ..., '21:00', '22:00']） */
    private function endTimes(array $week): array
    {
        if ($week === []) {
            return [];
        }

        $times = array_keys(reset($week));

        return [...array_slice($times, 1), config('whenfree.day_end')];
    }
}    
