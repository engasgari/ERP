<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends CrmModel
{
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'party_id', 'first_name', 'last_name', 'job_title', 'department',
        'mobile', 'phone', 'email', 'is_decision_maker', 'is_influencer',
        'is_primary', 'description', 'status', 'assigned_user_id',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_decision_maker' => 'boolean',
        'is_influencer' => 'boolean',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getStatusLabelAttribute(): string
    {
        return self::CONTACT_STATUSES[$this->status] ?? $this->status;
    }
}
