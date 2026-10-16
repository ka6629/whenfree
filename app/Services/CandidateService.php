<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 候補を作る処理と、返事をする処理（Issue #14）。
 *
 * 決まりに合わないときは、何も保存せずに例外を投げる。
 * - チームのメンバーでない → AuthorizationException
 * - 値が決まりに合わない → ValidationException（画面ではフォームのエラーとして出せる）
 */
class CandidateService
{
    /**
     * 候補を作る。
     *
     * @param  string  $date  日付（例：'2026-10-19'）
     * @param  string  $startTime  開始の時刻（例：'09:00'）
     * @param  string  $endTime  終了の時刻（例：'10:00'）
     * @param  int|null  $neededCount  必要な人数（null ならチーム全員）
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(
        User $creator,
        int $teamId,
        string $date,
        string $startTime,
        string $endTime,
        ?string $title = null,
        ?int $neededCount = null,
    ): Candidate {
        $this->ensureMember($teamId, $creator, 'このチームのメンバーではないので、候補を作れません。');

        // 「当日」はアプリの時刻の基準（config/app.php の timezone）で決める
        if (Carbon::parse($date)->startOfDay()->lt(today())) {
            throw ValidationException::withMessages(['date' => '過去の日付の候補は作れません。']);
        }

        // 日をまたぐ候補は扱わない（Issue #5 と同じ）
        if (Carbon::parse($endTime)->lte(Carbon::parse($startTime))) {
            throw ValidationException::withMessages(['end_time' => '終了の時刻は、開始の時刻より後にしてください。']);
        }

        if ($neededCount !== null && ($neededCount < 1 || $neededCount > $this->memberCount($teamId))) {
            throw ValidationException::withMessages(['needed_count' => '必要な人数は、1人以上・チームの人数以下にしてください。']);
        }

        return Candidate::create([
            'team_id' => $teamId,
            'created_by' => $creator->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'title' => $title,
            'needed_count' => $neededCount,
            'status' => Candidate::OPEN,
        ]);
    }

    /**
     * 候補に返事をする。2回目からは前の返事を上書きする。
     *
     * @param  string  $answer  Response::YES か Response::NO
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function answer(Candidate $candidate, User $user, string $answer): Response
    {
        $this->ensureMember($candidate->team_id, $user, 'このチームのメンバーではないので、返事できません。');

        if (! in_array($answer, [Response::YES, Response::NO], true)) {
            throw ValidationException::withMessages(['answer' => '返事は「はい」か「いいえ」で答えてください。']);
        }

        // 確定（decided）の候補には答え直しができる（Issue #8 で決めたこと）
        if ($candidate->status === Candidate::CANCELED) {
            throw ValidationException::withMessages(['answer' => '取り消しになった候補には返事できません。']);
        }

        return Response::updateOrCreate(
            ['candidate_id' => $candidate->id, 'user_id' => $user->id],
            ['answer' => $answer],
        );
    }

    /**
     * Team のリレーションが入るまでは、remainingCount() と同じく team_user を直接見る。
     *
     * @throws AuthorizationException
     */
    private function ensureMember(int $teamId, User $user, string $message): void
    {
        $isMember = DB::table('team_user')
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException($message);
        }
    }

    private function memberCount(int $teamId): int
    {
        return DB::table('team_user')->where('team_id', $teamId)->count();
    }
}
