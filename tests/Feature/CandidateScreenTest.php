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

    /** #19 完成条件：メンバーがフォームから候補を作ると、一覧に open の候補が出る */
    public function test_member_can_create_a_candidate_from_the_form(): void
    {
        [$team, $members] = $this->teamWithMembers(3);

        $this->actingAs($members[0])
            ->post("/teams/{$team->id}/candidates", [
                'date' => '2026-10-20',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'title' => '中間発表の練習',
                'needed_count' => '2',
            ])
            ->assertRedirect("/teams/{$team->id}/candidates");

        $candidate = Candidate::sole();
        $this->assertSame(Candidate::OPEN, $candidate->status);
        $this->assertSame($members[0]->id, $candidate->created_by);
        $this->assertSame(2, $candidate->needed_count);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee('中間発表の練習')
            ->assertSee('募集中');
    }

    /** #19 完成条件：タイトルと必要な人数は、空でも作れる */
    public function test_title_and_needed_count_can_be_empty(): void
    {
        [$team, $members] = $this->teamWithMembers(2);

        $this->actingAs($members[0])
            ->post("/teams/{$team->id}/candidates", [
                'date' => '2026-10-19',
                'start_time' => '18:00',
                'end_time' => '19:00',
                'title' => '',
                'needed_count' => '',
            ])
            ->assertSessionHasNoErrors();

        $candidate = Candidate::sole();
        $this->assertNull($candidate->title);
        $this->assertNull($candidate->needed_count);
    }

    /** #19 完成条件：決まりに合わないときは、作れずにエラーが出る */
    public function test_cannot_create_a_candidate_that_breaks_the_rules(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $valid = ['date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '10:00'];

        $cases = [
            ['date', ['date' => '2026-10-18']],             // 過去の日付
            ['end_time', ['end_time' => '09:00']],          // 終了が開始と同じ
            ['needed_count', ['needed_count' => '3']],      // チームの人数より多い
            ['needed_count', ['needed_count' => '0']],      // 0人
            ['needed_count', ['needed_count' => '-1']],     // マイナス
        ];

        foreach ($cases as [$errorKey, $change]) {
            $this->actingAs($members[0])
                ->post("/teams/{$team->id}/candidates", [...$valid, ...$change])
                ->assertSessionHasErrors($errorKey);
        }

        $this->assertSame(0, Candidate::count());
    }

    /** #19 完成条件：日付や時刻を入れていないときは、作れずにエラーが出る */
    public function test_date_and_times_are_required(): void
    {
        [$team, $members] = $this->teamWithMembers(2);

        $this->actingAs($members[0])
            ->post("/teams/{$team->id}/candidates", ['title' => '日付なし'])
            ->assertSessionHasErrors(['date', 'start_time', 'end_time']);

        $this->assertSame(0, Candidate::count());
    }

    /** #19 完成条件：メンバーでない人は、候補を作れない（403） */
    public function test_non_member_cannot_create_a_candidate(): void
    {
        [$team] = $this->teamWithMembers(2);

        $this->actingAs(User::factory()->create())
            ->post("/teams/{$team->id}/candidates", ['date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '10:00'])
            ->assertForbidden();

        $this->assertSame(0, Candidate::count());
    }

    /** 完成条件（#23）：候補を作った人は、一覧のボタンから確定・取り消しにできる */
    public function test_creator_can_decide_or_cancel_from_the_list(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $decided = $this->candidate($team, ['created_by' => $members[0]->id]);
        $canceled = $this->candidate($team, ['created_by' => $members[0]->id]);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSee(route('candidates.status', $decided))
            ->assertSee('確定する')
            ->assertSee('取り消す');

        $this->actingAs($members[0])
            ->post("/candidates/{$decided->id}/status", ['status' => Candidate::DECIDED])
            ->assertRedirect("/teams/{$team->id}/candidates")
            ->assertSessionHas('status', '候補を確定しました。');

        $this->actingAs($members[0])
            ->post("/candidates/{$canceled->id}/status", ['status' => Candidate::CANCELED])
            ->assertSessionHas('status', '候補を取り消しました。');

        $this->assertSame(Candidate::DECIDED, $decided->fresh()->status);
        $this->assertSame(Candidate::CANCELED, $canceled->fresh()->status);
    }

    /** 完成条件（#23）：候補を作った人のほかは、確定・取り消しにできない（403・ボタンも出ない） */
    public function test_others_cannot_decide_or_cancel(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team, ['created_by' => $members[0]->id]);

        $this->actingAs($members[1])
            ->get("/teams/{$team->id}/candidates")
            ->assertDontSee(route('candidates.status', $candidate));

        $this->actingAs($members[1])
            ->post("/candidates/{$candidate->id}/status", ['status' => Candidate::DECIDED])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post("/candidates/{$candidate->id}/status", ['status' => Candidate::CANCELED])
            ->assertForbidden();

        $this->assertSame(Candidate::OPEN, $candidate->fresh()->status);
    }

    /** 完成条件（#23）：一覧で候補の状態が分かり、募集中でない候補にはボタンが出ない */
    public function test_list_shows_status_and_hides_buttons_when_not_open(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $this->candidate($team, ['created_by' => $members[0]->id, 'date' => '2026-10-20', 'status' => Candidate::OPEN]);
        $decided = $this->candidate($team, ['created_by' => $members[0]->id, 'date' => '2026-10-21', 'status' => Candidate::DECIDED]);
        $canceled = $this->candidate($team, ['created_by' => $members[0]->id, 'date' => '2026-10-22', 'status' => Candidate::CANCELED]);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/candidates")
            ->assertSeeInOrder(['募集中', '確定', '取り消し'])
            ->assertDontSee(route('candidates.status', $decided))
            ->assertDontSee(route('candidates.status', $canceled))
            ->assertDontSee(route('candidates.answer', $canceled));
    }

    /** 確定した候補を取り消しにはできない（エラーが出て、状態は変わらない） */
    public function test_cannot_change_a_decided_candidate(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team, ['created_by' => $members[0]->id, 'status' => Candidate::DECIDED]);

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/status", ['status' => Candidate::CANCELED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Candidate::DECIDED, $candidate->fresh()->status);
    }

    /** 状態に配列などが送られても 500 にならず、入力のエラーになる */
    public function test_status_must_be_a_string(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team, ['created_by' => $members[0]->id]);

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/status", ['status' => [Candidate::DECIDED]])
            ->assertSessionHasErrors('status');

        $this->assertSame(Candidate::OPEN, $candidate->fresh()->status);

        // 作った人でなければ、入力が正しくなくても 403 になる
        $this->actingAs($members[1])
            ->post("/candidates/{$candidate->id}/status", ['status' => [Candidate::DECIDED]])
            ->assertForbidden();
    }

    /** 返事に配列などが送られても 500 にならず、入力のエラーになる */
    public function test_answer_must_be_a_string(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $candidate = $this->candidate($team);

        $this->actingAs($members[0])
            ->post("/candidates/{$candidate->id}/answer", ['answer' => [Response::YES]])
            ->assertSessionHasErrors('answer');

        $this->assertSame(0, $candidate->responses()->count());
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
