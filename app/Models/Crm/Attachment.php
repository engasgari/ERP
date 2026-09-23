<?php

namespace App\Models\Crm;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends CrmModel
{
    protected $table = 'crm_attachments';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'party_id', 'disk', 'path',
        'original_name', 'mime_type', 'size', 'created_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        if (! $this->size) {
            return '-';
        }

        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 1).' MB';
        }

        return number_format($this->size / 1024, 0).' KB';
    }
}
