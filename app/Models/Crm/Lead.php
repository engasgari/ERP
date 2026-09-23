<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends CrmModel
{
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'number', 'title', 'first_name', 'last_name', 'company_name',
        'phone', 'mobile', 'email', 'source_id', 'status', 'rating',
        'assigned_user_id', 'description', 'estimated_value', 'expected_close_date',
        'party_id', 'contact_id', 'converted_party_id', 'converted_contact_id',
        'converted_opportunity_id', 'converted_at', 'converted_by', 'lost_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'expected_close_date' => 'date',
        'converted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function convertedParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'converted_party_id');
    }

    public function convertedOpportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'converted_opportunity_id');
    }

    public function convertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activitable')
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('completed_at')
            ->orderByDesc('id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES_LEAD[$this->status] ?? $this->status;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim("{$this->first_name} {$this->last_name}") ?: $this->title;
    }

    public function getContactNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
