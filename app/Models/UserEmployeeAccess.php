<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmployeeAccess extends Model
{
    protected $table = 'user_employee_access';

    protected $fillable = [
        'user_id',
        'employee_id',
        'can_view_work_logs',
        'can_manage_work_logs',
        'can_view_salaries',
        'can_manage_salaries',
    ];

    protected $casts = [
        'can_view_work_logs' => 'boolean',
        'can_manage_work_logs' => 'boolean',
        'can_view_salaries' => 'boolean',
        'can_manage_salaries' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
