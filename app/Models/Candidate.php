<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
