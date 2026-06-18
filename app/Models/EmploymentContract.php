<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'employee_id', 'employment_order_id', 'contract_type', 'start_date', 'end_date', 'body', 'status', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employmentOrder(): BelongsTo
    {
        return $this->belongsTo(EmploymentOrder::class);
    }
}
