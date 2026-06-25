<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waitlist extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_PROMOTED = 'promoted';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'patient_id',
        'slot_id',
        'priority',
        'status',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'slot_id');
    }
}
