<?php

namespace App\Models\Crm;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends CrmModel
{
    use SoftDeletes;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'number', 'title', 'party_id', 'contact_id', 'lead_id',
        'pipeline_id', 'stage_id', 'assigned_user_id', 'amount', 'probability',
        'expected_amount', 'expected_close_date', 'source_id', 'status',
        'description', 'lost_reason', 'won_at', 'lost_at',
        'invoice_id', 'project_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'probability' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'expected_close_date' => 'date',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activitable')
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('completed_at')
            ->orderByDesc('id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
