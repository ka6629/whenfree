<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    // 自分のチーム一覧（作成・参加のフォームも同じ画面）
    public function index(Request $request)
    {
        $teams = $request->user()->teams()->get();

        return view('teams.index', compact('teams'));
    }

    // チームを作る
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'invite_code' => $this->makeInviteCode(),
        ]);

        $team->users()->attach($request->user());

        return redirect()->route('teams.show', $team)
            ->with('status', 'チームを作成しました。');
    }

    // 招待コードでチームに参加する
    public function join(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string'],
        ]);

        $team = Team::where('invite_code', Str::lower(trim($validated['invite_code'])))->first();

        if (! $team) {
            return back()->withErrors(['invite_code' => '招待コードが見つかりません。'])->withInput();
        }

        if ($team->users()->whereKey($request->user()->id)->exists()) {
            return back()->withErrors(['invite_code' => 'すでにこのチームに参加しています。'])->withInput();
        }

        $team->users()->attach($request->user());

        return redirect()->route('teams.show', $team)
            ->with('status', 'チームに参加しました。');
    }

    // チームの詳細（メンバーだけ）
    public function show(Request $request, Team $team)
    {
        abort_unless($team->users()->whereKey($request->user()->id)->exists(), 403);

        $members = $team->users()->get();

        return view('teams.show', compact('team', 'members'));
    }

    // 重ならない招待コードを作る（英小文字・数字6けた）
    private function makeInviteCode(): string
    {
        do {
            $code = Str::lower(Str::random(6));
        } while (Team::where('invite_code', $code)->exists());

        return $code;
    }
}