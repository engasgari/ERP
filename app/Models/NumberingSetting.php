<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSetting extends Model
{
    protected $fillable = [
        'document_key',
        'label',
        'prefix',
        'next_number',
        'padding',
        'reuse_deleted_numbers',
        'sort_order',
    ];

    protected $casts = [
        'reuse_deleted_numbers' => 'boolean',
    ];
}
