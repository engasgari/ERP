<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingAttachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'created_by'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
