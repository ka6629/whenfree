<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\TeamWeekService;
use Illuminate\Http\Request;

class TeamWeekController extends Controller
{
    // チームの1週間（メンバーだけ・今日から7日間）
    public function show(Request $request, Team $team, TeamWeekService $service)
    {
        abort_unless($team->users()->whereKey($request->user()->id)->exists(), 403);

        $memberCount = $team->users()->count();
        $week = $memberCount > 0 ? $service->forTeam($team) : [];

        return view('teams.week', compact('team', 'memberCount', 'week'));
    }
}
