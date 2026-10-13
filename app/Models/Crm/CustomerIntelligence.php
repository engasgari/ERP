<?php

namespace App\Models\Crm;

use App\Models\Ai\AiRun;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerIntelligence extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INSUFFICIENT = 'insufficient_data';

    public const STATUS_FAILED = 'failed';

    public const REVIEW_PENDING = 'pending_review';

    public const REVIEW_ACCEPTED = 'accepted';

    public const REVIEW_REJECTED = 'rejected';

    public const REVIEW_EXPIRED = 'expired';

    public const REVIEW_APPLIED = 'applied';

    protected $table = 'crm_customer_intelligence';

    protected $fillable = [
        'party_id', 'ai_run_id', 'status', 'review_status', 'identity_status', 'confidence',
        'result', 'prompt_version', 'model', 'failure_reason', 'requested_by',
        'reviewed_by', 'reviewed_at', 'generated_at', 'sources_checked_at',
    ];

    protected $casts = [
        'result' => 'array',
        'reviewed_at' => 'datetime',
        'generated_at' => 'datetime',
        'sources_checked_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CustomerIntelligenceSource::class, 'customer_intelligence_id');
    }

    public function verifiedSources(): HasMany
    {
        return $this->sources()->where('is_verified', true);
    }
}
