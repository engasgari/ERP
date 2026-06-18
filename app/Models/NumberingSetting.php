<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSetting extends Model
{
    protected $fillable = ['document_key', 'prefix', 'next_number', 'padding'];
}
