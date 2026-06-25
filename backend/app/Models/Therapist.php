<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Therapist extends Model
{
    protected $fillable = [
        'staff_id',
        'name',
        'specialty',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
