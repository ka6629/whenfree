<?php

namespace App\Models;

use Database\Factories\ResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['candidate_id', 'user_id', 'answer'])]
class Response extends Model
{
    /** @use HasFactory<ResponseFactory> */
    use HasFactory;

    public const YES = 'yes'; // はい

    public const NO = 'no';   // いいえ

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
