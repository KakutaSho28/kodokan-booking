<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_type',
        'action',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
    ];
}
