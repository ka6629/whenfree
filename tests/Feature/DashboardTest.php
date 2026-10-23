<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Candidate;
use App\Models\Response;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_自分のチームが表示される(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()]);
        $team = Team::create(['name' => '中間発表チーム', 'invite_code' => 'abc123']);
        $team->users()->attach($user);

                $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('中間発表チーム')
            ->assertSee(route('teams.week', $team), false);
    }

    public function test_予定を長く更新していないと案内が出る(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()->subDays(30)]);

        $this->actingAs($user)->get('/dashboard')->assertSee('予定を確認してください');
    }

    public function test_予定を1回も登録していないと案内が出る(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => null]);

        $this->actingAs($user)->get('/dashboard')->assertSee('まだ予定を登録していません');
    }

    public function test_最近更新した人には案内が出ない(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('予定を確認してください');
    }
        public function test_未回答の候補の数が出る(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()]);
        $other = User::factory()->create();
        $team = Team::create(['name' => 'テスト', 'invite_code' => 'abc123']);
        $team->users()->attach([$user->id, $other->id]);

        // 募集中・未回答（数える）
        Candidate::factory()->create(['team_id' => $team->id, 'created_by' => $other->id]);
        // 募集中・返事済み（数えない）
        $answered = Candidate::factory()->create(['team_id' => $team->id, 'created_by' => $other->id]);
        Response::factory()->create(['candidate_id' => $answered->id, 'user_id' => $user->id, 'answer' => 'yes']);
        // 取り消し（数えない）
        Candidate::factory()->canceled()->create(['team_id' => $team->id, 'created_by' => $other->id]);
        // 確定（数えない）
        Candidate::factory()->decided()->create(['team_id' => $team->id, 'created_by' => $other->id]);
        $this->actingAs($user)->get('/dashboard')->assertSee('未回答 1件');

    }

    public function test_全部に返事をしたら未回答は出ない(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()]);
        $team = Team::create(['name' => 'テスト', 'invite_code' => 'abc123']);
        $team->users()->attach($user);

        $candidate = Candidate::factory()->create(['team_id' => $team->id, 'created_by' => $user->id]);
        Response::factory()->create(['candidate_id' => $candidate->id, 'user_id' => $user->id, 'answer' => 'no']);

        $this->actingAs($user)->get('/dashboard')->assertDontSee('未回答');
    }

    /** #57：最後の区切り（毎週日曜 21:00）より前に更新した人には、7日以内でも案内が出る。前の「7日」の説明は出ない */
    public function test_区切りより前に更新した人には案内が出る(): void
    {
        $this->travelTo('2026-10-20 12:00:00'); // 「今」を 2026-10-20（火）12:00 に固定する。最後の区切りは 10/18（日）21:00
        $user = User::factory()->create(['schedule_updated_at' => '2026-10-18 20:59:00']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('予定を確認してください')
            ->assertSeeText('最後に更新したのは 2026/10/18 20:59 です。')
            ->assertSeeText('毎週日曜 21:00 を過ぎると、全員がいったん「不明」になります。')
            ->assertDontSee('日を超えて');
    }

    /** #57：区切りちょうど（日曜 21:00:00）に更新した人には、案内が出ない（1週間の画面と同じ決まり） */
    public function test_区切りちょうどに更新した人には案内が出ない(): void
    {
        $this->travelTo('2026-10-20 12:00:00');
        $user = User::factory()->create(['schedule_updated_at' => '2026-10-18 21:00:00']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('予定を確認してください')
            ->assertSeeText('最後に更新した日：2026/10/18 21:00');
    }
}
