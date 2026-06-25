<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentSlot extends Model
{
    protected $fillable = [
        'therapist_id',
        'date',
        'starts_at',
        'ends_at',
        'capacity',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    protected $appends = [
        'booked_count',
        'is_available',
        'availability_mark',
    ];

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(Waitlist::class, 'slot_id');
    }

    public function getBookedCountAttribute(): int
    {
        if ($this->relationLoaded('appointments')) {
            return $this->appointments->where('status', 'booked')->count();
        }

        return $this->appointments()->where('status', 'booked')->count();
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->booked_count < $this->capacity;
    }

    public function getAvailabilityMarkAttribute(): string
    {
        return $this->is_available ? '○' : '×';
    }
}
