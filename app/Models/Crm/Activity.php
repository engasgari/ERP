<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends CrmModel
{
    protected $table = 'crm_activities';

    protected $fillable = [
        'type', 'subject', 'description', 'activitable_type', 'activitable_id',
        'party_id', 'assigned_user_id', 'due_at', 'completed_at', 'status', 'created_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function activitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::ACTIVITY_TYPES[$this->type] ?? $this->type;
    }
}
