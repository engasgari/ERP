<?php

namespace App\Events;

use App\Models\Employee;
use App\Models\EmploymentOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeTerminated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Employee $employee, public EmploymentOrder $employmentOrder)
    {
    }
}
