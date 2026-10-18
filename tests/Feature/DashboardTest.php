<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            ->assertSee('中間発表チーム');
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
}
