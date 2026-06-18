<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmployeeHistory extends Model
{
    protected $table = 'employee_history';

    protected $fillable = ['employee_id', 'source_type', 'source_id', 'event', 'title', 'effective_date', 'old_values', 'new_values', 'created_by'];

    protected $casts = ['effective_date' => 'date', 'old_values' => 'array', 'new_values' => 'array'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
