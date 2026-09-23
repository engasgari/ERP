<?php

namespace App\Services\Crm;

use App\Models\Party;
use App\Models\PartyType;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Support\Arr;

class PartyCreationService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly CrmDuplicateGuardService $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCustomer(array $data, User $creator): Party
    {
        $this->duplicates->assertUniqueCustomer($data);

        PartyType::ensureDefaults();
        $customerType = PartyType::where('name', 'customer')->firstOrFail();

        $party = Party::create([
            'code' => $this->numbering->next('party', 'P-'),
            'detail_code' => $this->numbering->next('party_detail', 'D-'),
            'kind' => $data['kind'] ?? 'company',
            'name' => $data['name'],
            'national_id' => Arr::get($data, 'national_id'),
            'phone' => Arr::get($data, 'phone'),
            'mobile' => Arr::get($data, 'mobile'),
            'email' => Arr::get($data, 'email'),
            'address' => Arr::get($data, 'address'),
            'is_active' => true,
        ]);

        $party->types()->sync([$customerType->id]);

        return $party;
    }
}
