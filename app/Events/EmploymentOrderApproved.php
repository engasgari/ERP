<?php

namespace App\Events;

use App\Models\EmploymentOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmploymentOrderApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public EmploymentOrder $employmentOrder)
    {
    }
}
