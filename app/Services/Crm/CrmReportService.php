<?php

namespace App\Services\Crm;

use App\Models\User;
use App\Repositories\Crm\CrmReportRepository;

class CrmReportService
{
    public function __construct(private readonly CrmReportRepository $reports) {}

    public function summary(User $user): array
    {
        return $this->reports->summary($user);
    }

    public function leadsByStatus(User $user): array
    {
        return $this->reports->leadsByStatus($user);
    }

    public function opportunitiesByStage(User $user): array
    {
        return $this->reports->opportunitiesByStage($user);
    }
}
