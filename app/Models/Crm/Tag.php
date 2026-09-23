<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends CrmModel
{
    protected $table = 'crm_tags';

    protected $fillable = ['name', 'color'];
}
