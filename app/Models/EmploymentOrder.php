<?php

namespace App\Models;

use App\Support\Hr\IranLaborEmploymentOrderCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'issue_date',
        'employee_id',
        'position_id',
        'job_id',
        'job_group',
        'job_rank',
        'job_base',
        'organization_unit_id',
        'cost_center_code',
        'workshop_code',
        'default_project_id',
        'order_type',
        'decree_reason',
        'employment_type',
        'insurance_status',
        'marital_status',
        'effective_date',
        'end_date',
        'base_salary',
        'daily_wage',
        'hourly_rate',
        'monthly_work_hours',
        'daily_work_hours',
        'housing_allowance',
        'food_allowance',
        'child_allowance',
        'transportation_allowance',
        'marriage_allowance',
        'children_allowance',
        'children_count',
        'seniority_pay',
        'job_allowance',
        'hardship_allowance',
        'shift_allowance',
        'other_insurable_benefits',
        'other_non_insurable_benefits',
        'fixed_benefits',
        'fixed_deductions',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'effective_date' => 'date',
        'end_date' => 'date',
        'base_salary' => 'decimal:2',
        'daily_wage' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'monthly_work_hours' => 'decimal:2',
        'daily_work_hours' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'food_allowance' => 'decimal:2',
        'child_allowance' => 'decimal:2',
        'transportation_allowance' => 'decimal:2',
        'marriage_allowance' => 'decimal:2',
        'children_allowance' => 'decimal:2',
        'seniority_pay' => 'decimal:2',
        'job_allowance' => 'decimal:2',
        'hardship_allowance' => 'decimal:2',
        'shift_allowance' => 'decimal:2',
        'other_insurable_benefits' => 'decimal:2',
        'other_non_insurable_benefits' => 'decimal:2',
        'fixed_benefits' => 'array',
        'fixed_deductions' => 'array',
        'approved_at' => 'datetime',
        'children_count' => 'integer',
        'job_group' => 'integer',
        'job_rank' => 'integer',
        'job_base' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'default_project_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EmploymentOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderTypeLabel(): string
    {
        return IranLaborEmploymentOrderCatalog::orderTypes()[$this->order_type] ?? (string) $this->order_type;
    }

    public function employmentTypeLabel(): string
    {
        return IranLaborEmploymentOrderCatalog::employmentTypes()[$this->employment_type] ?? (string) $this->employment_type;
    }

    public function insuranceStatusLabel(): string
    {
        return IranLaborEmploymentOrderCatalog::insuranceStatuses()[$this->insurance_status] ?? (string) $this->insurance_status;
    }

    public function totalInsurableWage(): float
    {
        if ($this->relationLoaded('lines') ? $this->lines->isNotEmpty() : $this->lines()->exists()) {
            return round((float) $this->lines
                ->where('type', 'earning')
                ->where('is_insurable', true)
                ->sum('amount'), 2);
        }

        $total = 0.0;

        foreach (IranLaborEmploymentOrderCatalog::wageComponents() as $component) {
            if (! $component['insurable']) {
                continue;
            }

            $total += (float) ($this->{$component['field']} ?? 0);
        }

        foreach ((array) $this->fixed_benefits as $amount) {
            $total += (float) $amount;
        }

        return round($total, 2);
    }

    public function totalBenefits(): float
    {
        if ($this->relationLoaded('lines') ? $this->lines->isNotEmpty() : $this->lines()->exists()) {
            return round((float) $this->lines->where('type', 'earning')->sum('amount'), 2);
        }

        $total = 0.0;

        foreach (IranLaborEmploymentOrderCatalog::wageComponents() as $component) {
            $total += (float) ($this->{$component['field']} ?? 0);
        }

        foreach ((array) $this->fixed_benefits as $amount) {
            $total += (float) $amount;
        }

        return round($total, 2);
    }

    public function totalNonInsurableBenefits(): float
    {
        return round($this->totalBenefits() - $this->totalInsurableWage(), 2);
    }
}
