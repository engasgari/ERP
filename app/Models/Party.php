<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Crm\Activity as CrmActivity;
use App\Models\Crm\Contact as CrmContact;
use App\Models\Crm\CustomerProfile;
use App\Models\Crm\Lead as CrmLead;
use App\Models\Crm\Note as CrmNote;
use App\Models\Crm\Opportunity as CrmOpportunity;
use App\Models\Crm\SoldDevice;
use App\Models\Crm\Task as CrmTask;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'detail_code',
        'kind',
        'name',
        'economic_code',
        'registration_number',
        'national_id',
        'phone',
        'mobile',
        'email',
        'postal_code',
        'address',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(PartyType::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function accountingLines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function crmProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'party_id');
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'party_id');
    }

    public function crmOpportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'party_id');
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'party_id');
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'party_id')
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('completed_at')
            ->orderByDesc('id');
    }

    public function crmTasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'party_id');
    }

    public function crmSoldDevices(): HasMany
    {
        return $this->hasMany(SoldDevice::class, 'party_id');
    }

    public function crmNotes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'party_id');
    }

    public function scopeCustomers($query)
    {
        return $query->whereHas('types', fn ($types) => $types->where('name', 'customer'));
    }

    public function isCustomer(): bool
    {
        return $this->types()->where('name', 'customer')->exists();
    }

    public function getLedgerBalanceAttribute(): float
    {
        return (float) $this->accountingLines()->sum('debit') - (float) $this->accountingLines()->sum('credit');
    }
}
