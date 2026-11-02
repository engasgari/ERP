<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'registration_number',
        'economic_code',
        'national_id',
        'postal_code',
        'province',
        'city',
        'phone',
        'address',
        'default_vat_rate',
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2',
    ];
}
