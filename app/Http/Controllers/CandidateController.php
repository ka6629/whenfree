<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\Team;
use App\Services\CandidateService;
use Illuminate\Http\Request;

/**
 * 候補と返事の画面（Issue #18・#19）。
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
     * 候補を作る（Issue #19）。入力の形だけここで確かめ、決まりは CandidateService に任せる。
     */
    public function store(Request $request, Team $team)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:255'],
            'needed_count' => ['nullable', 'integer'],
        ], [
            'date.*' => '日付を入れてください。',
            'start_time.*' => '開始の時刻を入れてください。',
            'end_time.*' => '終了の時刻を入れてください。',
            'title.*' => 'タイトルは255文字までにしてください。',
            'needed_count.*' => '必要な人数は数字で入れてください。',
        ]);

        $this->service->create(
            $request->user(),
            $team->id,
            $validated['date'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['title'] ?? null,
            isset($validated['needed_count']) ? (int) $validated['needed_count'] : null,
        );

        return redirect()->route('candidates.index', $team)
            ->with('status', '候補を作りました。');
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
