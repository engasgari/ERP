<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartyType extends Model
{
    protected $fillable = ['name', 'title'];

    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class);
    }
}
