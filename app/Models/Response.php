<?php

namespace App\Models;

use Database\Factories\ResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['candidate_id', 'user_id', 'answer'])]
class Response extends Model
{
    /** @use HasFactory<ResponseFactory> */
    use HasFactory;

    public const YES = 'yes'; // はい

    public const NO = 'no';   // いいえ

    /** 返事を画面に出すことば。まだ返事をしていない（null）ときは「まだ」 */
    public static function labelFor(?string $answer): string
    {
        return match ($answer) {
            self::YES => 'はい',
            self::NO => 'いいえ',
            default => 'まだ',
        };
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * その人の、候補ごとの今の返事（候補の id => yes / no）。
     *
     * @param  Collection<int, int>  $candidateIds
     * @return Collection<int, string>
     */
    public static function answersOf(User $user, Collection $candidateIds): Collection
    {
        return static::where('user_id', $user->id)
            ->whereIn('candidate_id', $candidateIds)
            ->pluck('answer', 'candidate_id');
    }
}
