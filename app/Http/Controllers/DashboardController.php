<?php

namespace App\Http\Controllers;

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

        return view('dashboard', compact('teams', 'updatedAt', 'isStale', 'staleDays'));
    }
}
