<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING];

    public const COST_KNOWN_ESTIMATED = 'estimated';

    public const COST_REPORTED = 'reported';

    public const COST_UNKNOWN = 'unknown';

    protected $table = 'ai_runs';

    protected $fillable = [
        'uuid', 'agent', 'status', 'provider', 'model', 'prompt_version', 'correlation_id',
        'idempotency_key', 'user_id', 'subject_type', 'subject_id', 'input_type',
        'input_payload', 'output_payload', 'input_tokens', 'output_tokens',
        'estimated_cost_usd', 'reported_cost_usd', 'cost_status', 'attempts',
        'error_code', 'error_message', 'started_at', 'finished_at', 'duration_ms',
    ];

    /** Payloads are sanitized before storage but are still customer-related; keep them out of serialization. */
    protected $hidden = ['input_payload', 'output_payload'];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'reported_cost_usd' => 'decimal:6',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function effectiveCostUsd(): ?float
    {
        if ($this->reported_cost_usd !== null) {
            return (float) $this->reported_cost_usd;
        }

        return $this->estimated_cost_usd !== null ? (float) $this->estimated_cost_usd : null;
    }
}
