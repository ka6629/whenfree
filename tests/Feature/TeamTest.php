<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_チームを作ると自分がメンバーになり招待コードが作られる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/teams', ['name' => '中間発表チーム']);

        $team = Team::first();
        $this->assertSame('中間発表チーム', $team->name);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}$/', $team->invite_code);
        $this->assertTrue($team->users->contains($user));
    }

    public function test_招待コードで参加できる_大文字でもよい(): void
    {
        $team = Team::create(['name' => 'テスト', 'invite_code' => 'abc123']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/teams/join', ['invite_code' => 'ABC123']);

        $this->assertTrue($team->users()->whereKey($user->id)->exists());
    }

    public function test_間違った招待コードではエラーになる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/teams/join', ['invite_code' => 'zzz999'])
            ->assertSessionHasErrors('invite_code');
    }

    public function test_同じチームに2回参加できない(): void
    {
        $team = Team::create(['name' => 'テスト', 'invite_code' => 'abc123']);
        $user = User::factory()->create();
        $team->users()->attach($user);

        $this->actingAs($user)
            ->post('/teams/join', ['invite_code' => 'abc123'])
            ->assertSessionHasErrors('invite_code');

        $this->assertSame(1, $team->users()->count());
    }

    public function test_メンバーでない人はチームの詳細を見られない(): void
    {
        $team = Team::create(['name' => 'テスト', 'invite_code' => 'abc123']);
        $user = User::factory()->create();

        $this->actingAs($user)->get("/teams/{$team->id}")->assertForbidden();
    }
}