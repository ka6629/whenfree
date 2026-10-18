<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #41 の完成条件（「予定は変わっていません」のボタン）を確かめる。
 */
class ScheduleConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 07:00:00'); // 「今」を 2026-10-19（月）7:00 に固定する
    }

    /** 完成条件：押すと自分の最終更新日が今になり、予定そのものは変わらない */
    public function test_confirm_sets_own_last_updated_to_now_without_changing_schedules(): void
    {
        $a = $this->user('Aさん', '2026-10-11 07:00:00'); // 8日前＝表では不明
        $schedule = $a->schedules()->create([
            'type' => 'weekly', 'day_of_week' => 1,
            'start_time' => '09:00', 'end_time' => '10:30', 'title' => '授業',
        ]);
        $before = $schedule->fresh()->toArray();

        $this->actingAs($a)->post(route('schedules.confirm'))
            ->assertRedirect(route('schedules.index'))
            ->assertSessionHas('status');

        $this->assertSame('2026-10-19 07:00:00', $a->fresh()->schedule_updated_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Schedule::count());
        $this->assertSame($before, $schedule->fresh()->toArray());
    }

    /** 完成条件：ほかの人の最終更新日は変えられない */
    public function test_confirm_does_not_change_other_users(): void
    {
        $a = $this->user('Aさん', '2026-10-11 07:00:00');
        $b = $this->user('Bさん', '2026-10-11 07:00:00');
        $c = $this->user('Cさん', null);

        $this->actingAs($a)->post(route('schedules.confirm'));

        $this->assertSame('2026-10-11 07:00:00', $b->fresh()->schedule_updated_at->format('Y-m-d H:i:s'));
        $this->assertNull($c->fresh()->schedule_updated_at);
    }

    /** ログインしていない人は押せない（ログインの画面へ） */
    public function test_guest_cannot_confirm(): void
    {
        $this->post(route('schedules.confirm'))->assertRedirect(route('login'));
    }

    private function user(string $name, ?string $updatedAt): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['schedule_updated_at' => $updatedAt])->save();

        return $user;
    }
}
