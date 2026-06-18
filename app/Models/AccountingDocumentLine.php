<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingDocumentLine extends Model
{
    protected $fillable = [
        'accounting_document_id',
        'chart_account_id',
        'detail_account_id',
        'party_id',
        'project_id',
        'cost_center',
        'description',
        'debit',
        'credit',
        'currency',
        'exchange_rate',
        'bank_account_id',
        'cashbox_id',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'chart_account_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'accounting_document_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function detailAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'detail_account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function scopeForAccount($query, ?int $accountId)
    {
        return $accountId ? $query->where('chart_account_id', $accountId) : $query;
    }

    public function scopeForParty($query, ?int $partyId)
    {
        return $partyId ? $query->where('party_id', $partyId) : $query;
    }

    public function scopeForProject($query, ?int $projectId)
    {
        return $projectId ? $query->where('project_id', $projectId) : $query;
    }

    public function scopeForCostCenter($query, ?string $costCenter)
    {
        $costCenter = trim((string) $costCenter);

        return $costCenter !== ''
            ? $query->where('cost_center', 'like', "%{$costCenter}%")
            : $query;
    }
}
