<?php

namespace App\Support;

use App\Models\Crm\Opportunity;

class CrmOpportunityDialPhone
{
    /**
     * @return array{display: string, tel: string, source: string}|null
     */
    public static function resolve(Opportunity $opportunity): ?array
    {
        $opportunity->loadMissing(['party:id,name,mobile,phone', 'contact:id,mobile,phone,first_name,last_name']);

        $candidates = [
            ['value' => $opportunity->contact?->mobile, 'source' => 'موبایل مخاطب'],
            ['value' => $opportunity->contact?->phone, 'source' => 'تلفن مخاطب'],
            ['value' => $opportunity->party?->mobile, 'source' => 'موبایل مشتری'],
            ['value' => $opportunity->party?->phone, 'source' => 'تلفن مشتری'],
        ];

        foreach ($candidates as $candidate) {
            $tel = self::toTelUri($candidate['value']);

            if ($tel !== null) {
                return [
                    'display' => self::displayNumber($candidate['value']),
                    'tel' => $tel,
                    'source' => $candidate['source'],
                ];
            }
        }

        return null;
    }

    public static function toTelUri(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', trim($phone));

        if ($normalized === null || $normalized === '' || strlen(preg_replace('/\D/', '', $normalized)) < 8) {
            return null;
        }

        return 'tel:'.$normalized;
    }

    private static function displayNumber(?string $phone): string
    {
        return trim((string) $phone);
    }
}
