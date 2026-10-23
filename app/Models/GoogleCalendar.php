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
     * 最後に読み込んでから $minutes 分を過ぎた（または1回も読み込んでいない）カレンダー。持ち主もまとめて取る
     *
     * @return Collection<int, self>
     */
    public static function needsSync(iterable $userIds, int $minutes): Collection
    {
        return static::with('user')
            ->whereIn('user_id', collect($userIds))
            ->where(fn ($query) => $query->whereNull('synced_at')->orWhere('synced_at', '<', now()->subMinutes($minutes)))
            ->get();
    }

    /** 画面に出す「最後に読み込んだ日時」（例 2026/10/19 07:00） */
    public function syncedAtLabel(): string
    {
        return $this->synced_at?->format('Y/m/d H:i') ?? 'まだ';
    }
}
