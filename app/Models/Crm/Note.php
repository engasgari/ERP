<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends CrmModel
{
    use SoftDeletes;

    protected $table = 'crm_notes';

    protected $fillable = ['body', 'notable_type', 'notable_id', 'party_id', 'created_by'];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
