<?php

namespace App\Repositories\Crm;

use App\Models\Party;

class CrmPartyMatchRepository
{
    public function findExisting(?string $mobile, ?string $email, ?string $nationalId, ?string $companyName): ?Party
    {
        $query = Party::query()->customers();

        if ($nationalId) {
            $match = (clone $query)->where('national_id', $nationalId)->first();
            if ($match) {
                return $match;
            }
        }

        foreach (array_filter([$mobile, $email]) as $value) {
            $match = (clone $query)->where(function ($q) use ($value) {
                $q->where('mobile', $value)->orWhere('email', $value)->orWhere('phone', $value);
            })->first();

            if ($match) {
                return $match;
            }
        }

        if ($companyName) {
            return (clone $query)->where('name', $companyName)->first();
        }

        return null;
    }
}
