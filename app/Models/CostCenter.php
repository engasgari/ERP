<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'description'];

    protected $casts = ['is_active' => 'boolean'];
}
