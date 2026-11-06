<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'title',
        'type',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    // この予定の持ち主
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}