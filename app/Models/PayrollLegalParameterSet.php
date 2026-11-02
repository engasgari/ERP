<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollLegalParameterSet extends Model
{
    protected $fillable = [
        'jalali_year',
        'effective_from',
        'effective_to',
        'title',
        'source_ref',
        'status',
        'parameters',
    ];

    protected $casts = [
        'jalali_year' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'parameters' => 'array',
    ];

    public function parameter(string $key, mixed $default = null): mixed
    {
        return data_get($this->parameters, $key, $default);
    }
}
