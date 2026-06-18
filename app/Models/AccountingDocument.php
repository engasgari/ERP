<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class AccountingDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fiscal_year_id',
        'fiscal_period_id',
        'number',
        'document_date',
        'type',
        'status',
        'currency',
        'branch_id',
        'source_type',
        'source_id',
        'description',
        'notes',
        'created_by',
        'posted_at',
        'posted_by',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(AccountingAttachment::class, 'attachable');
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AccountingAudit::class, 'auditable');
    }

    public function getDebitTotalAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getCreditTotalAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->debit_total - $this->credit_total) < 0.01;
    }

    public function getIsAutomaticAttribute(): bool
    {
        return $this->source_type !== null || $this->source_id !== null;
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForFiscalYear($query, ?int $fiscalYearId)
    {
        return $fiscalYearId ? $query->where('fiscal_year_id', $fiscalYearId) : $query;
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        return $branchId && Schema::hasColumn($this->getTable(), 'branch_id')
            ? $query->where('branch_id', $branchId)
            : $query;
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($builder) => $builder->whereDate('document_date', '>=', $from))
            ->when($to, fn ($builder) => $builder->whereDate('document_date', '<=', $to));
    }

    public function scopeSearch($query, ?string $search)
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function ($builder) use ($search) {
            $builder->where('number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
