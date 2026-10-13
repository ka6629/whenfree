<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #8 の完成条件（候補と返事のテーブル・モデル）を確かめる。
 */
class CandidateResponseTest extends TestCase
{
    use RefreshDatabase;

    /** 完成条件：同じ人が同じ候補に2回返事をしても、行は1つ（上書き）になる */
    public function test_answering_twice_overwrites_the_same_row(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        Response::updateOrCreate(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id], ['answer' => Response::YES]);
        Response::updateOrCreate(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id], ['answer' => Response::NO]);

        $this->assertSame(1, $candidate->responses()->count());
        $this->assertSame(Response::NO, $candidate->responses()->first()->answer);
    }

    /** 同じ人の返事を2行入れようとすると、unique でエラーになる */
    public function test_cannot_insert_two_responses_for_the_same_person(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        Response::factory()->yes()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        Response::factory()->no()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);
    }

    /** 完成条件：3人のチームで1人が返事をすると、あと2人になる */
    public function test_remaining_count_decreases_when_a_member_answers(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        $this->assertSame(3, $candidate->remainingCount());

        Response::factory()->yes()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        $this->assertSame(2, $candidate->remainingCount());
    }

    /** 「いいえ」の返事も、返事をした人に数える */
    public function test_no_answer_also_counts_as_answered(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);

        Response::factory()->no()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        $this->assertSame(2, $candidate->remainingCount());
    }

    /** 完成条件：メンバーが増えると、あと何人も増える */
    public function test_remaining_count_increases_when_a_member_joins(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        Response::factory()->yes()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        $this->joinTeam($candidate->team_id, User::factory()->create());

        $this->assertSame(3, $candidate->remainingCount());
    }

    /** 完成条件：返事をした人がチームを抜けても、残りの人数はずれない */
    public function test_remaining_count_ignores_answers_from_members_who_left(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        Response::factory()->yes()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        DB::table('team_user')->where('team_id', $candidate->team_id)->where('user_id', $members[0]->id)->delete();

        $this->assertSame(2, $candidate->remainingCount()); // 残った2人はまだ返事をしていない
    }

    /** ほかのチームのメンバーと返事は数えない */
    public function test_remaining_count_ignores_other_teams(): void
    {
        [$candidate] = $this->candidateWithMembers(3);
        [$other, $otherMembers] = $this->candidateWithMembers(2);

        Response::factory()->yes()->create(['candidate_id' => $other->id, 'user_id' => $otherMembers[0]->id]);

        $this->assertSame(3, $candidate->remainingCount());
        $this->assertSame(1, $other->remainingCount());
    }

    /** 候補を消すと、返事も一緒に消える */
    public function test_deleting_a_candidate_deletes_its_responses(): void
    {
        [$candidate, $members] = $this->candidateWithMembers(3);
        Response::factory()->yes()->create(['candidate_id' => $candidate->id, 'user_id' => $members[0]->id]);

        $candidate->delete();

        $this->assertSame(0, Response::count());
    }

    /** 候補の最初の状態は 募集中（open） */
    public function test_new_candidate_is_open(): void
    {
        [$candidate] = $this->candidateWithMembers(1);

        $this->assertSame(Candidate::OPEN, $candidate->fresh()->status);
    }

    /**
     * メンバーが $count 人いるチームと、そのチームの候補を作る。
     *
     * @return array{0: Candidate, 1: Collection<int, User>}
     */
    private function candidateWithMembers(int $count): array
    {
        // Team のモデルと Factory はまだ無いので、teams・team_user に直接入れる
        $teamId = DB::table('teams')->insertGetId([
            'name' => 'テストチーム',
            'invite_code' => Str::random(16),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $members = User::factory()->count($count)->create();
        foreach ($members as $member) {
            $this->joinTeam($teamId, $member);
        }

        $candidate = Candidate::factory()->create(['team_id' => $teamId, 'created_by' => $members[0]->id]);

        return [$candidate, $members];
    }

    private function joinTeam(int $teamId, User $user): void
    {
        DB::table('team_user')->insert([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
