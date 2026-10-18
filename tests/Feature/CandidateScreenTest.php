<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Response;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Issue #18 の完成条件（候補の一覧と返事の画面）を確かめる。
 */
class CandidateScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 12:00:00'); // 「今日」を 2026-10-19（月）に固定する
    }

    /** 完成条件：チームのメンバーは、そのチームの候補の一覧を見られる（日付と時刻の順） */
    public function test_member_can_see_candidates_in_order(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $this->candidate($team, ['date' => '2026-10-21', 'start_time' => '09:00', 'title' => '3番目']);
        $this->candidate($team, ['date' => '2026-10-20', 'start_time' => '13:00', 'title' => '2番目']);
        $this->candidate($team, ['date' => '2026-10-20', 'start_time' => '09:00', 'title' => '1番目']);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertOk()
            ->assertSeeInOrder(['1番目', '2番目', '3番目']);
    }

    /** ほかのチームの候補は出ない */
    public function test_candidates_of_other_teams_are_not_listed(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        [$otherTeam] = $this->teamWithMembers(2);
        $this->candidate($team, ['title' => '自分のチームの候補']);
        $this->candidate($otherTeam, ['title' => 'ほかのチームの候補']);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('自分のチームの候補')
            ->assertDontSee('ほかのチームの候補');
    }

    /** 完成条件：メンバーでない人は、一覧を見られない（403） */
    public function test_non_member_cannot_see_candidates(): void
    {
        [$team] = $this->teamWithMembers(2);

        $this->actingAs(User::factory()->create())
            ->get("/teams/{$team->id}/candidates")
            ->assertForbidden();
    }

    /** 完成条件：「はい」「いいえ」を押すと返事が保存され、一覧に今の返事が出る */
    public function test_member_can_answer_and_see_the_answer(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/answer", ['answer' => Response::YES])
            ->assertRedirect("/teams/{$team->id}/candidates");

        $this->assertSame(Response::YES, $candidate->responses()->where('user_id', $members[0]->id)->value('answer'));

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('今の返事：はい');
    }

    /** 完成条件：もう一度押すと、答え直しができる */
    public function test_member_can_change_the_answer(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->actingAs($members[0])->post("/candidates/{$candidate->id}/answer", ['answer' => Response::YES]);
        $this->actingAs($members[0])->post("/candidates/{$candidate->id}/answer", ['answer' => Response::NO]);

        $this->assertSame(1, $candidate->responses()->count());

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('今の返事：いいえ');
    }

    /** 完成条件：「あと◯人の返事待ち」が、返事をすると1人減る */
    public function test_remaining_count_decreases_after_answering(): void
    {
        [$team, $members] = $this->teamWithMembers(3);
        $candidate = $this->candidate($team);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('あと3人の返事待ち');

        $this->actingAs($members[0])->post("/candidates/{$candidate->id}/answer", ['answer' => Response::NO]);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('あと2人の返事待ち');
    }

    /** 完成条件：取り消しの候補には返事できない（ボタンも出ない） */
    public function test_cannot_answer_a_canceled_candidate(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team, ['status' => Candidate::CANCELED]);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('取り消し')
            ->assertDontSee(route('candidates.answer', $candidate));

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/answer", ['answer' => Response::YES])
            ->assertSessionHasErrors('answer');

        $this->assertSame(0, $candidate->responses()->count());
    }

    /** はい・いいえ以外の返事はエラーになる */
    public function test_answer_must_be_yes_or_no(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/answer", ['answer' => 'maybe'])
            ->assertSessionHasErrors('answer');

        $this->assertSame(0, $candidate->responses()->count());
    }

    /** メンバーでない人は、返事できない（403） */
    public function test_non_member_cannot_answer(): void
    {
        [$team] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->actingAs(User::factory()->create())
            ->post("/candidates/{$candidate->id}/answer", ['answer' => Response::YES])
            ->assertForbidden();

        $this->assertSame(0, $candidate->responses()->count());
    }

    /** 完成条件：ログインしていない人は、ログインの画面に移る */
    public function test_guest_is_redirected_to_login(): void
    {
        [$team] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->get("/teams/{$team->id}/candidates")->assertRedirect('/login');
        $this->post("/candidates/{$candidate->id}/answer", ['answer' => Response::YES])->assertRedirect('/login');
    }

    /**
     * メンバーが $count 人いるチームを作る。
     *
     * @return array{0: Team, 1: Collection<int, User>}
     */
    private function teamWithMembers(int $count): array
    {
        static $codeNumber = 0;

        $team = Team::create(['name' => 'テスト', 'invite_code' => sprintf('code%02d', ++$codeNumber)]);
        $members = User::factory()->count($count)->create();
        $team->users()->attach($members);

        return [$team, $members];
    }

    private function candidate(Team $team, array $attributes = []): Candidate
    {
        return Candidate::factory()->create([
            'team_id' => $team->id,
            'created_by' => $team->users()->first()->id,
            ...$attributes,
        ]);
    }
}
