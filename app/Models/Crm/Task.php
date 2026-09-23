<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends CrmModel
{
    protected $table = 'crm_tasks';

    protected $fillable = [
        'title', 'description', 'assigned_user_id', 'created_by',
        'due_at', 'reminder_at', 'priority', 'status',
        'taskable_type', 'taskable_id', 'party_id', 'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function taskable(): MorphTo
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

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES_TASK[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES_TASK[$this->status] ?? $this->status;
    }
}
