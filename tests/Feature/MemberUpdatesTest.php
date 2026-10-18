<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Issue #41 の完成条件（メンバーごとの最終更新日と「不明」）を確かめる。
 */
class MemberUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00'); // 「今」を 2026-10-19（月）7:00 に固定する
    }

    /** 完成条件：チームの1週間のページに、メンバーごとの最終更新日が出る */
    public function test_week_page_shows_last_updated_date_of_each_member(): void
    {
        $team = $this->team();
        $a = $this->member($team, 'Aさん', '2026-10-18 21:30:00');
        $this->member($team, 'Bさん', '2026-10-13 07:00:00'); // 6日前

        $rows = $this->rows($this->actingAs($a)->get(route('teams.week', $team)));

        $this->assertSame(['Aさん', 'Bさん'], array_keys($rows)); // id の順
        $this->assertStringContainsString('最後に更新した日：2026/10/18 21:30', $rows['Aさん']);
        $this->assertStringContainsString('最後に更新した日：2026/10/13 07:00', $rows['Bさん']);
        $this->assertStringNotContainsString('不明', $rows['Aさん']);
        $this->assertStringNotContainsString('不明', $rows['Bさん']);
    }

    /** 完成条件：7日を超えた人と、1回も登録していない人に「不明」と出る */
    public function test_stale_and_unregistered_members_are_unknown(): void
    {
        $team = $this->team();
        $a = $this->member($team, 'Aさん', '2026-10-19 06:00:00');
        $this->member($team, 'Bさん', '2026-10-11 07:00:00'); // 8日前
        $this->member($team, 'Cさん', null);                  // 1回も登録していない
        $this->member($team, 'Dさん', '2026-10-12 07:00:00'); // ちょうど7日前＝表と同じく不明にしない

        $rows = $this->rows($this->actingAs($a)->get(route('teams.week', $team)));

        $this->assertStringContainsString('？ 不明', $rows['Bさん']);
        $this->assertStringContainsString('まだ登録していません', $rows['Cさん']);
        $this->assertStringContainsString('？ 不明', $rows['Cさん']);
        $this->assertStringNotContainsString('不明', $rows['Dさん']);
        $this->assertStringNotContainsString('不明', $rows['Aさん']);
    }

    /** ほかのチームの人は、一覧に出ない */
    public function test_other_team_members_are_not_listed(): void
    {
        $team = $this->team();
        $a = $this->member($team, 'Aさん', '2026-10-19 06:00:00');
        $this->member($this->team(), '別のチームの人', '2026-10-19 06:00:00');

        $rows = $this->rows($this->actingAs($a)->get(route('teams.week', $team)));

        $this->assertSame(['Aさん'], array_keys($rows));
    }

    /**
     * 「メンバーの予定の更新」の一覧を「名前 => その行の文字」に読み替える。
     * 表の見方や表の枠にも「？ 不明」があるので、一覧の行だけを見る。
     *
     * @return array<string, string>
     */
    private function rows(TestResponse $response): array
    {
        $response->assertOk();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();

        $items = (new \DOMXPath($dom))->query('//ul[@id="member-updates"]/li');
        $this->assertGreaterThan(0, $items->length, '一覧が見つからない');

        $rows = [];
        foreach ($items as $li) {
            $name = trim($li->getElementsByTagName('span')->item(0)->textContent);
            $rows[$name] = preg_replace('/\s+/u', ' ', trim($li->textContent));
        }

        return $rows;
    }

    private function team(): Team
    {
        return Team::create(['name' => 'テストチーム', 'invite_code' => Str::random(16)]);
    }

    private function member(Team $team, string $name, ?string $updatedAt): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['schedule_updated_at' => $updatedAt])->save();
        $team->users()->attach($user);

        return $user;
    }
}
