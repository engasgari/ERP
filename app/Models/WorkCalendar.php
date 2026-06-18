<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCalendar extends Model
{
    protected $fillable = [
        'code',
        'name',
        'jalali_year',
        'working_days',
        'weekend_days',
        'holidays',
        'is_default',
        'is_active',
        'description',
    ];

    protected $casts = [
        'working_days' => 'array',
        'weekend_days' => 'array',
        'holidays' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function workGroups(): HasMany
    {
        return $this->hasMany(WorkGroup::class);
    }
}
