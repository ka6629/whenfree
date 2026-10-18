<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #37 の完成条件（チームの1週間の画面）を確かめる。
 */
class TeamWeekScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00'); // 「今」を 2026-10-19（月）7:00 に固定する
    }

    /** 完成条件：チームのメンバーは、そのチームの1週間のページを開ける（今日から7日間・8:00〜21:00） */
    public function test_member_can_open_team_week(): void
    {
        [$team, $members] = $this->teamWithMembers(2);

        $this->actingAs($members[0])
            ->get("/teams/{$team->id}/week")
            ->assertOk()
            ->assertSee('表の見方')
            ->assertSeeInOrder(['10/19（月）', '10/25（日）'])
            ->assertSeeInOrder(['08:00', '21:00'])
            ->assertDontSee('22:00');
    }

    /** 完成条件：メンバーでない人は開けない（403） */
    public function test_non_member_cannot_open_team_week(): void
    {
        [$team] = $this->teamWithMembers(2);

        $this->actingAs(User::factory()->create())
            ->get("/teams/{$team->id}/week")
            ->assertForbidden();
    }

    /** 完成条件：ログインしていない人は、ログインの画面へ移る */
    public function test_guest_is_redirected_to_login(): void
    {
        [$team] = $this->teamWithMembers(2);

        $this->get("/teams/{$team->id}/week")
            ->assertRedirect(route('login'));
    }

    private function teamWithMembers(int $count): array
    {
        $team = Team::create(['name' => 'テストチーム', 'invite_code' => Str::random(16)]);

        $members = User::factory()->count($count)->create()->all();
        foreach ($members as $member) {
            $member->forceFill(['schedule_updated_at' => now()])->save();
            $team->users()->attach($member);
        }

        return [$team, $members];
    }
}
