<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawLog extends Model
{
    protected $fillable = ['employee_id', 'attendance_card_number', 'logged_at', 'direction', 'device_code', 'source', 'payload'];

    protected $casts = ['logged_at' => 'datetime', 'payload' => 'array'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
