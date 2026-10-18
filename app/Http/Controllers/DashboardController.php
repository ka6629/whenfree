<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $teams = $user->teams()->withCount('users')->get();

        // チームの1週間で「不明」になる日数とそろえる
        $staleDays = config('whenfree.stale_days', 7);
        $updatedAt = $user->schedule_updated_at;
        $isStale = ! $updatedAt || $updatedAt->lt(now()->subDays($staleDays));

        // チームごとの未回答の候補の数（募集中で、自分がまだ返事していないもの）
        $unanswered = Candidate::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->where('status', 'open')
            ->whereDoesntHave('responses', fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('team_id, count(*) as total')
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        return view('dashboard', compact('teams', 'updatedAt', 'isStale', 'staleDays', 'unanswered'));
    }
}