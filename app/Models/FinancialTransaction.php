<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
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
}
