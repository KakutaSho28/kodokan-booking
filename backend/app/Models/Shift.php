<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $fillable = [
        'staff_id',
        'work_date',
        'start_time',
        'end_time',
        'is_day_off',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'is_day_off' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
