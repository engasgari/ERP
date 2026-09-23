<?php

namespace App\Support;

use App\Models\Crm\Lead;

class CrmLeadDialPhone
{
    /**
     * @return array{display: string, tel: string, source: string}|null
     */
    public static function resolve(Lead $lead): ?array
    {
        $candidates = [
            ['value' => $lead->mobile, 'source' => 'موبایل سرنخ'],
            ['value' => $lead->phone, 'source' => 'تلفن سرنخ'],
        ];

        foreach ($candidates as $candidate) {
            $tel = CrmOpportunityDialPhone::toTelUri($candidate['value']);

            if ($tel !== null) {
                return [
                    'display' => trim((string) $candidate['value']),
                    'tel' => $tel,
                    'source' => $candidate['source'],
                ];
            }
        }

        return null;
    }
}
