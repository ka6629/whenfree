<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function weekly(array $override = []): array
    {
        return array_merge([
            'type' => 'weekly', 'day_of_week' => 1,
            'start_time' => '09:00', 'end_time' => '10:30', 'title' => '授業',
        ], $override);
    }

    public function test_毎週の予定を追加すると最後に更新した日が入る(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => null]);

        $this->actingAs($user)->post('/schedules', $this->weekly())->assertRedirect('/schedules');

        $this->assertSame(1, $user->schedules()->count());
        $this->assertNotNull($user->fresh()->schedule_updated_at);
    }

    public function test_単発の予定を追加できる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/schedules', [
            'type' => 'single', 'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00',
        ]);

        $this->assertSame(1, $user->schedules()->where('type', 'single')->count());
    }

    public function test_終了が開始以前の予定は追加できない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/schedules', $this->weekly(['start_time' => '10:00', 'end_time' => '10:00']))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, $user->schedules()->count());
    }

    public function test_毎週の予定は曜日が必要(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/schedules', $this->weekly(['day_of_week' => null]))
            ->assertSessionHasErrors('day_of_week');
    }

    public function test_自分の予定を編集すると最後に更新した日が変わる(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => now()->subDays(10)]);
        $schedule = $user->schedules()->create($this->weekly());

        $this->actingAs($user)->put("/schedules/{$schedule->id}", $this->weekly(['end_time' => '11:00']));

        $this->assertSame('11:00', substr($schedule->fresh()->end_time, 0, 5));
        $this->assertTrue($user->fresh()->schedule_updated_at->isToday());
    }

    public function test_自分の予定を削除できる(): void
    {
        $user = User::factory()->create(['schedule_updated_at' => null]);
        $schedule = $user->schedules()->create($this->weekly());

        $this->actingAs($user)->delete("/schedules/{$schedule->id}");

        $this->assertModelMissing($schedule);
        $this->assertNotNull($user->fresh()->schedule_updated_at);
    }

    public function test_他人の予定は編集も削除もできない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $schedule = $owner->schedules()->create($this->weekly());

        $this->actingAs($other)->put("/schedules/{$schedule->id}", $this->weekly())->assertForbidden();
        $this->actingAs($other)->delete("/schedules/{$schedule->id}")->assertForbidden();

        $this->assertModelExists($schedule);
    }

    public function test_過去の日付の単発の予定は追加できない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/schedules', [
                'type' => 'single', 'date' => now()->subDay()->toDateString(),
                'start_time' => '10:00', 'end_time' => '12:00',
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame(0, $user->schedules()->count());
    }

    public function test_単発の予定を編集・削除できる(): void
    {
        $user = User::factory()->create();
        $schedule = $user->schedules()->create([
            'type' => 'single', 'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($user)->put("/schedules/{$schedule->id}", [
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '13:00', 'end_time' => '15:00',
        ]);
        $this->assertSame('13:00', substr($schedule->fresh()->start_time, 0, 5));

        $this->actingAs($user)->delete("/schedules/{$schedule->id}");
        $this->assertModelMissing($schedule);
    }

    public function test_他人の予定の編集画面は開けない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $schedule = $owner->schedules()->create($this->weekly());

        $this->actingAs($other)->get("/schedules/{$schedule->id}/edit")->assertForbidden();
    }
}
