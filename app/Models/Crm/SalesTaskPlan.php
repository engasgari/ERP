<?php

namespace App\Models\Crm;

use App\Models\Ai\AiRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTaskPlan extends CrmModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INSUFFICIENT = 'insufficient_data';

    public const STATUS_FAILED = 'failed';

    protected $table = 'crm_sales_task_plans';

    protected $fillable = [
        'ai_run_id', 'status', 'prompt_version', 'model',
        'candidates_count', 'suggestions_count', 'failure_reason', 'planner_note',
        'requested_by', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'candidates_count' => 'integer',
        'suggestions_count' => 'integer',
    ];

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(SalesTaskSuggestion::class, 'plan_id');
    }
}
