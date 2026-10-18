<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\Team;
use App\Services\CandidateService;
use Illuminate\Http\Request;

/**
 * 候補と返事の画面（Issue #18）。
 *
 * 決まりの確かめは CandidateService に任せる。
 * - メンバーでない → AuthorizationException → 403
 * - 決まりに合わない → ValidationException → 一覧に戻ってエラーを出す
 */
class CandidateController extends Controller
{
    public function __construct(private CandidateService $service) {}

    /**
     * チームの候補の一覧（メンバーだけ）。
     */
    public function index(Request $request, Team $team)
    {
        abort_unless($team->users()->whereKey($request->user()->id)->exists(), 403);

        $candidates = Candidate::where('team_id', $team->id)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // 自分の今の返事（候補の id => yes / no）
        $myAnswers = Response::where('user_id', $request->user()->id)
            ->whereIn('candidate_id', $candidates->pluck('id'))
            ->pluck('answer', 'candidate_id');

        return view('candidates.index', compact('team', 'candidates', 'myAnswers'));
    }

    /**
     * はい・いいえで返事をする。2回目からは答え直しになる。
     */
    public function answer(Request $request, Candidate $candidate)
    {
        $this->service->answer($candidate, $request->user(), (string) $request->input('answer'));

        return redirect()->route('candidates.index', $candidate->team_id)
            ->with('status', '返事をしました。');
    }
}
