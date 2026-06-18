<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingAudit extends Model
{
    protected $fillable = ['auditable_type', 'auditable_id', 'event', 'user_id', 'old_values', 'new_values', 'ip_address'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
