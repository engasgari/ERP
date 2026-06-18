<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_number',
        'name',
        'party_id',
        'project_manager_id',
        'description',
        'status',
        'closed_at',
        'start_date',
        'end_date',
        'budget',
        'cost_center_code',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'budget' => 'decimal:2',
    ];

    public const STATUSES = [
        'planning' => 'برنامه‌ریزی',
        'active' => 'فعال',
        'procurement' => 'تامین کالا',
        'manufacturing' => 'در حال تولید',
        'testing' => 'کنترل کیفیت',
        'delivered' => 'تحویل شده',
        'closed' => 'بسته شده',
        'inactive' => 'غیرفعال',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function inventoryDocuments(): HasMany
    {
        return $this->hasMany(InventoryDocument::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function overheadAllocations(): HasMany
    {
        return $this->hasMany(ProjectOverheadAllocation::class);
    }

    public function costSnapshots(): HasMany
    {
        return $this->hasMany(ProjectCostSnapshot::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status ?? '-';
    }

    public function getTotalWarehouseCostAttribute(): float
    {
        return (float) $this->inventoryDocuments()
            ->whereIn('type', ['issue', 'consumption'])
            ->where('status', 'confirmed')
            ->join('inventory_document_lines as lines', 'lines.inventory_document_id', '=', 'inventory_documents.id')
            ->sum('lines.line_total');
    }

    public function getTotalIncomeAttribute(): float
    {
        return (float) $this->financialTransactions()
            ->where('type', 'income')
            ->sum('amount');
    }

    public function getTotalExpenseAttribute(): float
    {
        $financialExpenses = (float) $this->financialTransactions()
            ->where('type', 'expense')
            ->sum('amount');

        $laborCosts = (float) $this->workLogs()->sum('total_amount');

        return $financialExpenses + $laborCosts + $this->total_warehouse_cost;
    }

    public function getTotalLaborCostAttribute(): float
    {
        return (float) $this->workLogs()->sum('total_amount');
    }

    public function getTotalWorkHoursAttribute(): float
    {
        return (float) $this->workLogs()->sum('hours');
    }

    public function getActiveEmployeesAttribute()
    {
        return $this->workLogs()
            ->with('employee')
            ->get()
            ->pluck('employee')
            ->unique('id');
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_income - $this->total_expense;
    }

    public function getProfitPercentageAttribute(): float
    {
        if ($this->total_income > 0) {
            return ($this->net_profit / $this->total_income) * 100;
        }

        return 0;
    }

    public function getGrossProfitAttribute(): float
    {
        return $this->total_income - $this->total_expense;
    }

    public function getFinancialStatusAttribute(): string
    {
        if ($this->net_profit > 0) {
            return 'سودده';
        }

        if ($this->net_profit < 0) {
            return 'زیان‌ده';
        }

        return 'متوازن';
    }

    public function getDurationAttribute(): string
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInDays($this->end_date) . ' روز';
        }

        return 'تعیین نشده';
    }

    public function getDateStatusAttribute(): string
    {
        if (! $this->start_date || ! $this->end_date) {
            return 'بدون تاریخ';
        }

        $now = now();

        if ($now->lt($this->start_date)) {
            return 'آینده';
        }

        if ($now->gt($this->end_date)) {
            return 'پایان یافته';
        }

        return 'در جریان';
    }

    public function getDateStatusColorAttribute(): string
    {
        return match ($this->date_status) {
            'آینده' => 'bg-blue-100 text-blue-800',
            'در جریان' => 'bg-green-100 text-green-800',
            'پایان یافته' => 'bg-gray-100 text-gray-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }
}
