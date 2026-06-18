<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use SoftDeletes;

    protected $table = 'hr_jobs';

    protected $fillable = ['code', 'title', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
