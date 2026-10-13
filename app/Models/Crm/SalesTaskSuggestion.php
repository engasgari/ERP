<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTaskSuggestion extends CrmModel
{
    public const REVIEW_PENDING = 'pending_review';

    public const REVIEW_ACCEPTED = 'accepted';

    public const REVIEW_REJECTED = 'rejected';

    public const REVIEW_APPLIED = 'applied';

    protected $table = 'crm_sales_task_suggestions';

    protected $fillable = [
        'plan_id', 'candidate_key', 'candidate_kind', 'subject_type', 'subject_id',
        'party_id', 'assigned_user_id', 'title', 'description', 'priority', 'due_at',
        'due_offset_business_hours', 'rank', 'reason', 'supporting_facts',
        'review_status', 'applied_task_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'supporting_facts' => 'array',
        'rank' => 'integer',
        'due_offset_business_hours' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesTaskPlan::class, 'plan_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function appliedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'applied_task_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES_TASK[$this->priority] ?? $this->priority;
    }

    public function getKindLabelAttribute(): string
    {
        return match ($this->candidate_kind) {
            'new_lead_no_contact' => 'سرنخ بدون تماس',
            'opportunity_idle' => 'فرصت بدون پیگیری',
            'proforma_idle' => 'پیش‌فاکتور بدون پیگیری',
            'overdue_task' => 'وظیفه عقب‌افتاده',
            'dormant_customer' => 'مشتری بدون تماس',
            'high_value_opportunity' => 'فرصت با اهمیت بالا',
            'near_close_opportunity' => 'فرصت نزدیک به تصمیم',
            default => $this->candidate_kind,
        };
    }
}
