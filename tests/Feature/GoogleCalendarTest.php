<?php

namespace Tests\Feature;

use App\Models\GoogleBusyTime;
use App\Models\GoogleCalendar;
use App\Models\Team;
use App\Models\User;
use App\Services\AvailabilityCalculator;
use App\Services\GoogleCalendarService;
use App\Services\TeamWeekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #54 の完成条件（Google カレンダーの予定を読み込んで、チームの1週間に使う）を確かめる。
 */
class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00'); // 「今」を 2026-10-19（月）7:00 に固定する

        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/google-calendar/callback',
        ]);
    }

    /** 完成条件：外部のカレンダーをつなげられる（Google から戻ってきたら、トークンを保存して読み込む） */
    public function test_user_can_connect_google_calendar(): void
    {
        $this->fakeGoogle([['start' => '2026-10-19T10:00:00+09:00', 'end' => '2026-10-19T11:30:00+09:00']]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/google-calendar/connect')
            ->assertRedirectContains('accounts.google.com');
        $state = session('google_calendar_state');

        $this->actingAs($user)->get("/google-calendar/callback?code=abc&state={$state}")
            ->assertRedirect('/google-calendar');

        $calendar = GoogleCalendar::where('user_id', $user->id)->first();
        $this->assertSame('access-1', $calendar->access_token);
        $this->assertNotNull($calendar->synced_at);
        $this->assertNotNull($user->fresh()->schedule_updated_at); // 読み込めた人は「不明」にしない
        $this->assertDatabaseHas('google_busy_times', ['user_id' => $user->id, 'start_time' => '10:00', 'end_time' => '11:30']);
    }

    /** state が合わないときはつながない */
    public function test_callback_with_wrong_state_does_not_connect(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/google-calendar/connect');
        $this->actingAs($user)->get('/google-calendar/callback?code=abc&state=wrong')
            ->assertRedirect('/google-calendar');

        $this->assertDatabaseCount('google_calendars', 0);
        Http::assertNothingSent();
    }

    /** 完成条件：つないだカレンダーの予定がある時間は「埋まり」、手で入れた予定もこれまでどおり「埋まり」 */
    public function test_google_and_manual_schedules_are_busy(): void
    {
        [$team, [$alice, $bob]] = $this->teamWithMembers(2);
        $this->connected($alice);
        GoogleBusyTime::create(['user_id' => $alice->id, 'date' => '2026-10-19', 'start_time' => '10:00', 'end_time' => '11:00']);
        $bob->schedules()->create(['type' => 'single', 'date' => '2026-10-20', 'start_time' => '09:00', 'end_time' => '10:00']);

        $week = app(TeamWeekService::class)->forTeam($team);

        $this->assertSame([$alice->id], $week['2026-10-19']['10:00']['busy']);
        $this->assertSame(AvailabilityCalculator::FREE, $week['2026-10-19']['11:00']['team']);
        $this->assertSame([$bob->id], $week['2026-10-20']['09:00']['busy']);
    }

    /** 日をまたぐ予定は、日ごとに分けて読み込む */
    public function test_period_over_midnight_is_split_by_day(): void
    {
        $this->fakeGoogle([['start' => '2026-10-19T12:00:00Z', 'end' => '2026-10-20T01:00:00Z']]); // 日本時間 21:00〜翌10:00
        $user = User::factory()->create();

        app(GoogleCalendarService::class)->sync($this->connected($user));

        $rows = GoogleBusyTime::orderBy('date')->get()
            ->map(fn ($row) => [$row->date->format('Y-m-d'), substr($row->start_time, 0, 5), substr($row->end_time, 0, 5)])->all();
        $this->assertSame([['2026-10-19', '21:00', '23:59'], ['2026-10-20', '00:00', '10:00']], $rows);
    }

    /** 完成条件：ほかの人には予定の中身が見えない（freeBusy の権限だけをもらい、中身を保存する所もない） */
    public function test_only_free_busy_is_requested(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/google-calendar/connect')
            ->assertRedirectContains(urlencode('https://www.googleapis.com/auth/calendar.freebusy'))
            ->assertRedirectContains('state=');

        $this->assertNotContains('title', Schema::getColumnListing('google_busy_times'));
    }

    /** しばらく読み込んでいない人は、チームの1週間を開いたときに読み込み直す。トークンが切れていたら新しくもらう */
    public function test_stale_calendar_is_synced_with_refreshed_token(): void
    {
        $this->fakeGoogle([['start' => '2026-10-19T14:00:00+09:00', 'end' => '2026-10-19T15:00:00+09:00']]);
        [$team, [$alice]] = $this->teamWithMembers(1);
        $this->connected($alice)->update(['synced_at' => now()->subHour(), 'expires_at' => now()->subMinute()]);

        $this->actingAs($alice)->get("/teams/{$team->id}/week")->assertOk();

        Http::assertSent(fn (Request $request) => ($request['grant_type'] ?? null) === 'refresh_token');
        $this->assertDatabaseHas('google_busy_times', ['user_id' => $alice->id, 'start_time' => '14:00']);
    }

    /** Google に届かなくても、チームの1週間は開ける */
    public function test_team_week_opens_even_if_google_fails(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        [$team, [$alice]] = $this->teamWithMembers(1);
        $this->connected($alice)->update(['synced_at' => now()->subHour()]);

        $this->actingAs($alice)->get("/teams/{$team->id}/week")->assertOk();
    }

    /** つながりを外すと、読み込んだ時間も消える */
    public function test_user_can_disconnect(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->connected($user);
        GoogleBusyTime::create(['user_id' => $user->id, 'date' => '2026-10-19', 'start_time' => '10:00', 'end_time' => '11:00']);

        $this->actingAs($user)->delete('/google-calendar')->assertRedirect('/google-calendar');

        $this->assertDatabaseCount('google_calendars', 0);
        $this->assertDatabaseCount('google_busy_times', 0);
    }

    private function fakeGoogle(array $busy): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => $busy]]]),
        ]);
    }

    private function connected(User $user): GoogleCalendar
    {
        return GoogleCalendar::create([
            'user_id' => $user->id,
            'access_token' => 'access-0',
            'refresh_token' => 'refresh-0',
            'expires_at' => now()->addHour(),
            'synced_at' => now(),
        ]);
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
