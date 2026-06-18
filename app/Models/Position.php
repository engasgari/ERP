<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'job_id',
        'organization_unit_id',
        'supervisor_position_id',
        'capacity',
        'is_active',
        'description',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_position_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
