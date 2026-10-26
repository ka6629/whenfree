<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

// つないだ Google カレンダー（Issue #54）
#[Fillable(['user_id', 'access_token', 'refresh_token', 'expires_at', 'synced_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class GoogleCalendar extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    // つないだ人
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** その人がつないだカレンダー（つないでいなければ null） */
    public static function of(User $user): ?self
    {
        return static::where('user_id', $user->id)->first();
    }

    /**
     * チームのメンバーのうち、最後に読み込んでから $minutes 分を過ぎた（または1回も読み込んでいない）カレンダー。持ち主もまとめて取る
     *
     * @return Collection<int, self>
     */
    public static function needsSync(Team $team, int $minutes): Collection
    {
        return static::with('user')
            ->whereIn('user_id', $team->users()->select('users.id'))
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', now()->subMinutes($minutes)))
            ->get();
    }

    /**
     * Google からもらったトークンを保存する（つなぎ直したときは上書きする）
     *
     * @param  array  $token  Google の返事（access_token・refresh_token・expires_in）
     */
    public static function saveToken(User $user, array $token): self
    {
        return static::updateOrCreate(['user_id' => $user->id], [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);
    }

    /** 期限が切れて新しくもらったトークンに替える（refresh_token が来なければ前のものを使い続ける） */
    public function updateToken(array $token): void
    {
        $this->update(array_filter([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]));
    }

    /** 読み込めたことを記録する。読み込んだだけでは「確かめた」ことにしないので、「最後に更新した日」は変えない（#57） */
    public function markSynced(): void
    {
        $this->update(['synced_at' => now()]);
    }

    /** 画面に出す「最後に読み込んだ日時」（例 2026/10/19 07:00） */
    public function syncedAtLabel(): string
    {
        return $this->synced_at?->format('Y/m/d H:i') ?? 'まだ';
    }
}
