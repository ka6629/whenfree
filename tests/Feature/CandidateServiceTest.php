<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\User;
use App\Services\CandidateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Issue #14 の完成条件（候補を作る処理・返事をする処理）を確かめる。
 */
class CandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    private CandidateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 12:00:00'); // 「今日」を 2026-10-19（月）に固定する
        $this->service = new CandidateService;
    }

    /** 完成条件：メンバーが候補を作ると、open の候補が1つ増える */
    public function test_member_can_create_an_open_candidate(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        $candidate = $this->service->create($members[0], $teamId, '2026-10-20', '09:00', '10:00', '中間発表の練習');

        $this->assertSame(1, Candidate::count());
        $this->assertSame(Candidate::OPEN, $candidate->fresh()->status);
        $this->assertSame($members[0]->id, $candidate->created_by);
        $this->assertSame('中間発表の練習', $candidate->title);
    }

    /** 完成条件：メンバーでない人は、候補を作れない */
    public function test_non_member_cannot_create_a_candidate(): void
    {
        [$teamId] = $this->teamWithMembers(3);
        $outsider = User::factory()->create();

        $this->assertThrowsWithoutSaving(AuthorizationException::class, fn () => $this->service->create($outsider, $teamId, '2026-10-20', '09:00', '10:00'));
    }

    /** ほかのチームのメンバーも、候補を作れない */
    public function test_member_of_another_team_cannot_create_a_candidate(): void
    {
        [$teamId] = $this->teamWithMembers(3);
        [, $otherMembers] = $this->teamWithMembers(2);

        $this->assertThrowsWithoutSaving(AuthorizationException::class, fn () => $this->service->create($otherMembers[0], $teamId, '2026-10-20', '09:00', '10:00'));
    }

    /** 完成条件：終了の時刻が開始の時刻より前の候補は作れない（日をまたぐ候補も含む） */
    public function test_cannot_create_a_candidate_that_ends_before_it_starts(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->create($members[0], $teamId, '2026-10-20', '21:00', '01:00'));
    }

    /** 完成条件：終了の時刻が開始の時刻と同じ候補は作れない */
    public function test_cannot_create_a_candidate_that_ends_when_it_starts(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->create($members[0], $teamId, '2026-10-20', '09:00', '09:00'));
    }

    /** 完成条件：過去の日付の候補は作れない */
    public function test_cannot_create_a_candidate_in_the_past(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->create($members[0], $teamId, '2026-10-18', '09:00', '10:00'));
    }

    /** 完成条件：当日の候補は作れる（今の時刻より前の時間帯でも、日付で決める） */
    public function test_can_create_a_candidate_for_today(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        $this->service->create($members[0], $teamId, '2026-10-19', '09:00', '10:00');

        $this->assertSame(1, Candidate::count());
    }

    /** 完成条件：needed_count が null・1・チームの人数と同じとき、候補を作れる */
    public function test_can_create_with_valid_needed_count(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        foreach ([null, 1, 3] as $neededCount) {
            $candidate = $this->service->create($members[0], $teamId, '2026-10-20', '09:00', '10:00', null, $neededCount);
            $this->assertSame($neededCount, $candidate->fresh()->needed_count);
        }

        $this->assertSame(3, Candidate::count());
    }

    /** 完成条件：needed_count が0以下・チームの人数より多いとき、候補を作れない */
    public function test_cannot_create_with_invalid_needed_count(): void
    {
        [$teamId, $members] = $this->teamWithMembers(3);

        foreach ([0, -1, 4] as $neededCount) {
            $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->create($members[0], $teamId, '2026-10-20', '09:00', '10:00', null, $neededCount));
        }
    }

    /** 完成条件：yes / no の返事ができる */
    public function test_member_can_answer_yes_or_no(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        $this->service->answer($candidate, $members[0], Response::YES);
        $this->service->answer($candidate, $members[1], Response::NO);

        $this->assertSame(Response::YES, $this->answerOf($candidate, $members[0]));
        $this->assertSame(Response::NO, $this->answerOf($candidate, $members[1]));
        $this->assertSame(1, $candidate->remainingCount());
    }

    /** 完成条件：yes / no 以外の返事は例外になる */
    public function test_cannot_answer_other_than_yes_or_no(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        foreach (['maybe', 'YES', ''] as $answer) {
            $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->answer($candidate, $members[0], $answer));
        }
    }

    /** 完成条件：メンバーでない人は、返事できない */
    public function test_non_member_cannot_answer(): void
    {
        [$candidate] = $this->candidateWithMembers(3);
        $outsider = User::factory()->create();

        $this->assertThrowsWithoutSaving(AuthorizationException::class, fn () => $this->service->answer($candidate, $outsider, Response::YES));
    }

    /** 完成条件：同じ人が2回返事をすると、行は1つのまま、新しい答えになる */
    public function test_answering_twice_overwrites_the_answer(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        $this->service->answer($candidate, $members[0], Response::YES);
        $this->service->answer($candidate, $members[0], Response::NO);

        $this->assertSame(1, Response::count());
        $this->assertSame(Response::NO, $this->answerOf($candidate, $members[0]));
    }

    /** 完成条件：取り消しの候補には返事・答え直しができず、前の返事も変わらない */
    public function test_cannot_answer_a_canceled_candidate(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        $this->service->answer($candidate, $members[0], Response::YES);

        $candidate->update(['status' => Candidate::CANCELED]);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->answer($candidate, $members[0], Response::NO));
        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->answer($candidate, $members[1], Response::YES));
        $this->assertSame(Response::YES, $this->answerOf($candidate, $members[0]));
    }

    /** 完成条件：確定の候補には返事・答え直しができる */
    public function test_can_answer_a_decided_candidate(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        $this->service->answer($candidate, $members[0], Response::YES);

        $candidate->update(['status' => Candidate::DECIDED]);

        $this->service->answer($candidate, $members[0], Response::NO);
        $this->service->answer($candidate, $members[1], Response::YES);

        $this->assertSame(Response::NO, $this->answerOf($candidate, $members[0]));
        $this->assertSame(Response::YES, $this->answerOf($candidate, $members[1]));
    }

    /** 完成条件（#23）：候補を作った人は、募集中の候補を確定・取り消しにできる */
    public function test_creator_can_decide_or_cancel_an_open_candidate(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        $other = Candidate::factory()->create(['team_id' => $candidate->team_id, 'created_by' => $members[0]->id]);

        $this->service->changeStatus($candidate, $members[0], Candidate::DECIDED);
        $this->service->changeStatus($other, $members[0], Candidate::CANCELED);

        $this->assertSame(Candidate::DECIDED, $candidate->fresh()->status);
        $this->assertSame(Candidate::CANCELED, $other->fresh()->status);
    }

    /** 完成条件（#23）：候補を作った人のほかは、メンバーでも確定・取り消しにできない */
    public function test_others_cannot_decide_or_cancel(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        $this->assertThrowsWithoutSaving(AuthorizationException::class, fn () => $this->service->changeStatus($candidate, $members[1], Candidate::DECIDED));
        $this->assertThrowsWithoutSaving(AuthorizationException::class, fn () => $this->service->changeStatus($candidate, User::factory()->create(), Candidate::CANCELED));
    }

    /** 確定・取り消しにした候補は、元にも、もう一方にも変えられない */
    public function test_cannot_change_a_candidate_that_is_not_open(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        $this->service->changeStatus($candidate, $members[0], Candidate::DECIDED);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->changeStatus($candidate, $members[0], Candidate::CANCELED));
        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->changeStatus($candidate, $members[0], Candidate::OPEN));
    }

    /** 同時に操作されても、先に確定した状態を後の操作で上書きしない */
    public function test_cannot_overwrite_a_status_changed_at_the_same_time(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        $stale = Candidate::find($candidate->id); // 募集中のときに読み込んだもの

        $this->service->changeStatus($candidate, $members[0], Candidate::DECIDED);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->changeStatus($stale, $members[0], Candidate::CANCELED));
        $this->assertSame(Candidate::DECIDED, $candidate->fresh()->status);
    }

    /** 確定・取り消し以外の状態にはできない */
    public function test_status_must_be_decided_or_canceled(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        $this->assertThrowsWithoutSaving(ValidationException::class, fn () => $this->service->changeStatus($candidate, $members[0], 'unknown'));
    }

    /**
     * 例外が投げられ、候補と返事が1つも増えず・変わらないことを確かめる。
     *
     * @param  class-string<\Throwable>  $exception
     */
    private function assertThrowsWithoutSaving(string $exception, callable $action): void
    {
        $candidates = Candidate::orderBy('id')->get()->toArray();
        $responses = Response::orderBy('id')->get()->toArray();

        $this->assertThrows($action, $exception);

        $this->assertSame($candidates, Candidate::orderBy('id')->get()->toArray());
        $this->assertSame($responses, Response::orderBy('id')->get()->toArray());
    }

    private function answerOf(Candidate $candidate, User $user): ?string
    {
        return Response::where('candidate_id', $candidate->id)->where('user_id', $user->id)->value('answer');
    }

    /**
     * メンバーが $count 人いるチームと、そのチームの候補を作る。
     *
     * @return array{0: Candidate, 1: Collection<int, User>}
     */
    private function candidateWithMembers(int $count): array
    {
        [$teamId, $members] = $this->teamWithMembers($count);

        $candidate = Candidate::factory()->create(['team_id' => $teamId, 'created_by' => $members[0]->id]);

        return [$candidate, $members];
    }

    /**
     * メンバーが $count 人いるチームを作る。
     *
     * @return array{0: int, 1: Collection<int, User>}
     */
    private function teamWithMembers(int $count): array
    {
        // Team の Factory はまだ無いので、teams・team_user に直接入れる
        $teamId = DB::table('teams')->insertGetId([
            'name' => 'テストチーム',
            'invite_code' => Str::random(16),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $members = User::factory()->count($count)->create();
        foreach ($members as $member) {
            DB::table('team_user')->insert([
                'team_id' => $teamId,
                'user_id' => $member->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$teamId, $members];
    }
}
