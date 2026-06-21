<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AccountingDocument;
use App\Models\ChartAccount;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bank_account_id',
        'cashbox_id',
        'chart_account_id',
        'detail_account_id',
        'accounting_document_id',
        'type',
        'category',
        'amount',
        'transaction_date',
        'description',
        'reference_number',
        'attachment'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date'
    ];

    // رابطه با پروژه
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

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    public function detailAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'detail_account_id');
    }

    public function accountingDocument(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class);
    }

    // عنوان نوع تراکنش
    public function getTypeLabelAttribute()
    {
        return $this->type == 'income' ? 'درآمد' : 'هزینه';
    }

    // رنگ نوع تراکنش
    public function getTypeColorAttribute()
    {
        return $this->type == 'income' ? 'text-green-600' : 'text-red-600';
    }

    // آیکون نوع تراکنش
    public function getTypeIconAttribute()
    {
        return $this->type == 'income' ? '💰' : '💸';
    }

    // فرمت مبلغ
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount) . ' ریال';
    }

    // مبلغ با علامت
    public function getSignedAmountAttribute()
    {
        $sign = $this->type == 'income' ? '+' : '-';
        return $sign . number_format($this->amount) . ' ریال';
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->bankAccount) {
            return 'بانک: ' . $this->bankAccount->bank_name . ' - ' . $this->bankAccount->code;
        }

        if ($this->cashbox) {
            return 'صندوق: ' . $this->cashbox->name . ' - ' . $this->cashbox->code;
        }

        return '-';
    }

    public function getCodingLabelAttribute(): string
    {
        $parts = [];

        if ($this->chartAccount) {
            $parts[] = $this->chartAccount->code . ' - ' . $this->chartAccount->title;
        }

        if ($this->detailAccount) {
            $parts[] = $this->detailAccount->code . ' - ' . $this->detailAccount->title;
        }

        return $parts ? implode(' / ', $parts) : '-';
    }
}
