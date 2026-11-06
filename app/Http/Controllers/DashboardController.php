<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\TeamWeekService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TeamWeekService $service)
    {
        $user = $request->user();

        $teams = $user->teams()->withCount('users')->get();

        // チームの1週間と同じ決まりで「予定を確認してください」を出す（#57：最後の区切りより前に更新した人・1回も登録していない人）
        $updatedAt = $user->schedule_updated_at;
        $isStale = $service->isUnknown($user);
        $checkDay = config('whenfree.check_day');   // 区切りの曜日と時刻。案内の文に使う
        $checkTime = config('whenfree.check_time');

        // チームごとの未回答の候補の数（募集中で、自分がまだ返事していないもの）
        $unanswered = Candidate::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->where('status', Candidate::OPEN)
            ->whereDoesntHave('responses', fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('team_id, count(*) as total')
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        return view('dashboard', compact('teams', 'updatedAt', 'isStale', 'checkDay', 'checkTime', 'unanswered'));
    }
}
