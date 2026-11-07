<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\Team;
use App\Services\CandidateService;
use Illuminate\Http\Request;

/**
 * 候補と返事の画面（Issue #18・#19・#23・#24・#25）。
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

        // データを取るのはモデルに任せる
        $candidates = Candidate::forTeam($team)->get();
        $myAnswers = Response::answersOf($request->user(), $candidates->pluck('id'));
        // 「あと◯人の返事待ち」は候補ごとに数えず、まとめて数える（候補の id => 人数）
        $remainingCounts = Candidate::remainingCountsFor($team);

        // 画面に出す形にする：まだ返事をしていない、募集中の候補の数（Issue #25）
        $unansweredCount = $candidates
            ->where('status', Candidate::OPEN)
            ->reject(fn (Candidate $candidate) => $myAnswers->has($candidate->id))
            ->count();

        return view('candidates.index', compact('team', 'candidates', 'myAnswers', 'remainingCounts', 'unansweredCount'));
    }

    /**
     * 「あと◯人の返事待ち」だけを返す（Issue #24）。一覧の画面が数秒おきに取りに来る。
     * 例 {"12": 2, "13": 0}（候補の id => 人数）
     */
    public function counts(Request $request, Team $team)
    {
        abort_unless($team->users()->whereKey($request->user()->id)->exists(), 403);

        return response()->json(Candidate::remainingCountsFor($team));
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
        $this->service->answer($candidate, $request->user(), $this->stringInput($request, 'answer'));

        return redirect()->route('candidates.index', $candidate->team_id)
            ->with('status', '返事をしました。');
    }

    /**
     * 候補を確定・取り消しにする（Issue #23）。
     */
    public function changeStatus(Request $request, Candidate $candidate)
    {
        $this->service->changeStatus($candidate, $request->user(), $this->stringInput($request, 'status'));

        return redirect()->route('candidates.index', $candidate->team_id)
            ->with('status', $candidate->status === Candidate::DECIDED ? '候補を確定しました。' : '候補を取り消しました。');
    }

    /**
     * 配列などが送られても 500 にならないよう、文字列でなければ空にする。
     * 空のときは CandidateService が（メンバーかどうか等を確かめた後で）入力のエラーにする。
     */
    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }
}
