<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollImportLog extends Model
{
    protected $fillable = [
        'batch_number',
        'file_name',
        'imported_count',
        'failed_count',
        'errors',
        'created_by',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
