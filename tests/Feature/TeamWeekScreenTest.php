<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    /** 完成条件：チームの画面に「チームの1週間を見る →」のリンクがある */
    public function test_team_page_links_to_team_week(): void
    {
        [$team, $members] = $this->teamWithMembers(2);

        $this->actingAs($members[0])
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('チームの1週間を見る')
            ->assertSee(route('teams.week', $team));
    }

    /** 完成条件：予定と重なる枠に「埋まり」と出る（ほかの枠は「空き」） */
    public function test_busy_cell_shows_busy_label(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $members[0]->schedules()->create([
            'title' => 'ゼミ',
            'type' => 'single',
            'date' => '2026-10-21',
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);
        $members[0]->forceFill(['schedule_updated_at' => now()])->save();

        $cells = $this->cells($this->actingAs($members[0])->get("/teams/{$team->id}/week"));

        $this->assertSame('× 埋まり', $cells['10/21（水）']['13:00']);
        $this->assertSame('○ 空き', $cells['10/21（水）']['14:00']); // 予定が終わった後の枠
        $this->assertSame('○ 空き', $cells['10/20（火）']['13:00']); // 前の日の同じ時刻
        $this->assertSame(['× 埋まり' => 1, '○ 空き' => 97], $this->countLabels($cells));
    }

    /** 完成条件：更新が7日を超えた人がいる枠は「不明」。ただし埋まりの人がいる枠は「埋まり」 */
    public function test_unknown_cell_shows_unknown_label_but_busy_wins(): void
    {
        [$team, $members] = $this->teamWithMembers(2);
        $members[0]->schedules()->create([
            'title' => 'ゼミ',
            'type' => 'single',
            'date' => '2026-10-21',
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);
        $members[0]->forceFill(['schedule_updated_at' => now()])->save();
        $members[1]->forceFill(['schedule_updated_at' => now()->subDays(8)])->save(); // 8日前から更新していない

        $cells = $this->cells($this->actingAs($members[0])->get("/teams/{$team->id}/week"));

        $this->assertSame('× 埋まり', $cells['10/21（水）']['13:00']); // 埋まりの人がいる枠は「埋まり」
        $this->assertSame('？ 不明', $cells['10/21（水）']['14:00']);
        $this->assertSame(['× 埋まり' => 1, '？ 不明' => 97], $this->countLabels($cells));
    }

    /**
     * 画面の表を「日付の見出し => 時刻 => 枠の文字」に読み替える。
     * 表の見方の欄にも同じ文字があるので、assertSee ではなく表の枠だけを見る。
     *
     * @return array<string, array<string, string>>
     */
    private function cells(TestResponse $response): array
    {
        $response->assertOk();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();

        $table = $dom->getElementsByTagName('table')->item(0);
        $this->assertNotNull($table, '表が見つからない');

        $dates = [];
        foreach ($table->getElementsByTagName('thead')->item(0)->getElementsByTagName('th') as $i => $th) {
            if ($i > 0) { // 1列目は「時刻」
                $dates[] = trim($th->textContent);
            }
        }

        $cells = [];
        foreach ($table->getElementsByTagName('tbody')->item(0)->getElementsByTagName('tr') as $tr) {
            $time = trim($tr->getElementsByTagName('th')->item(0)->textContent);
            foreach ($tr->getElementsByTagName('td') as $i => $td) {
                $cells[$dates[$i]][$time] = trim($td->textContent);
            }
        }

        return $cells;
    }

    /**
     * 枠の文字ごとの数（7日×14枠＝98枠）
     *
     * @return array<string, int>
     */
    private function countLabels(array $cells): array
    {
        $counts = [];
        foreach ($cells as $times) {
            foreach ($times as $text) {
                $counts[$text] = ($counts[$text] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
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
