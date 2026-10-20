<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['team_id', 'created_by', 'date', 'start_time', 'end_time', 'title', 'needed_count', 'status'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    public const OPEN = 'open';         // 募集中

    public const DECIDED = 'decided';   // 確定

    public const CANCELED = 'canceled'; // 取り消し

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'needed_count' => 'integer',
        ];
    }

    /** 状態を画面に出すことば */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::OPEN => '募集中',
            self::DECIDED => '確定',
            self::CANCELED => '取り消し',
        };
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** 候補を出した人 */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * チームの候補を、日付・開始の時刻の順に並べて取る。
     * 使い方：Candidate::forTeam($team)->get()
     */
    #[Scope]
    protected function forTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->id)
            ->orderBy('date')
            ->orderBy('start_time');
    }

    /**
     * チームの候補ごとの「あと◯人の返事待ち」（候補の id => 人数）。
     *
     * 一覧の画面から10秒おきに呼ばれるので、候補が増えても問い合わせの回数が増えないようにまとめて数える。
     * 数え方は remainingCount() と同じ（メンバーのうち、まだ返事をしていない人数）。
     *
     * @return Collection<int, int>
     */
    public static function remainingCountsFor(Team $team): Collection
    {
        $memberCount = $team->users()->count();

        // 候補ごとの、返事をしたメンバーの人数（候補の id => 人数）
        $answeredCounts = Response::query()
            ->join('candidates', 'candidates.id', '=', 'responses.candidate_id')
            ->join('team_user', function ($join) {
                $join->on('team_user.user_id', '=', 'responses.user_id')
                    ->on('team_user.team_id', '=', 'candidates.team_id');
            })
            ->where('candidates.team_id', $team->id)
            ->groupBy('responses.candidate_id')
            ->selectRaw('responses.candidate_id, count(*) as answered')
            ->pluck('answered', 'candidate_id');

        return static::where('team_id', $team->id)
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => $memberCount - (int) ($answeredCounts[$id] ?? 0)]);
    }

    /**
     * あと何人の返事待ちか（チームのメンバーのうち、まだ返事をしていない人数）。
     * Team のリレーションが入るまでは team_user を直接数える。
     */
    public function remainingCount(): int
    {
        return DB::table('team_user')
            ->where('team_id', $this->team_id)
            ->whereNotIn('user_id', $this->responses()->select('user_id'))
            ->count();
    }
}
