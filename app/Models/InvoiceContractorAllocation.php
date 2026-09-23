<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceContractorAllocation extends Model
{
    protected $fillable = [
        'sale_invoice_id',
        'contractor_party_id',
        'purchase_invoice_id',
        'created_by',
        'updated_by',
    ];

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'sale_invoice_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'purchase_invoice_id');
    }
}
