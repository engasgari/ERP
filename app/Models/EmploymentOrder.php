<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'employee_id',
        'position_id',
        'job_id',
        'organization_unit_id',
        'cost_center_code',
        'default_project_id',
        'order_type',
        'employment_type',
        'insurance_status',
        'effective_date',
        'end_date',
        'base_salary',
        'hourly_rate',
        'housing_allowance',
        'food_allowance',
        'child_allowance',
        'transportation_allowance',
        'marriage_allowance',
        'children_allowance',
        'children_count',
        'seniority_pay',
        'fixed_benefits',
        'fixed_deductions',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'food_allowance' => 'decimal:2',
        'child_allowance' => 'decimal:2',
        'transportation_allowance' => 'decimal:2',
        'marriage_allowance' => 'decimal:2',
        'children_allowance' => 'decimal:2',
        'seniority_pay' => 'decimal:2',
        'fixed_benefits' => 'array',
        'fixed_deductions' => 'array',
        'approved_at' => 'datetime',
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
}
