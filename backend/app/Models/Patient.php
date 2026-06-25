<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'card_number',
        'name',
        'birth_date',
        'email',
        'is_first_visit',
        'has_rehab_clearance',
        'is_diagnosed',
        'assigned_therapist_id',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'is_first_visit' => 'boolean',
        'has_rehab_clearance' => 'boolean',
        'is_diagnosed' => 'boolean',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(Waitlist::class);
    }

    public function assignedTherapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class, 'assigned_therapist_id');
    }

    public function canBookRehab(): bool
    {
        return $this->is_diagnosed;
    }
}
