<?php

namespace App\Http\Controllers;

use App\Models\EmploymentOrder;
use App\Services\EmploymentOrderService;

class EmploymentOrderController extends Controller
{
    public function index()
    {
        return view('employment-orders.index');
    }

    public function approve(EmploymentOrder $employmentOrder, EmploymentOrderService $service)
    {
        abort_unless(request()->user()->hasPermission('employment-orders.approve'), 403);

        $service->approve($employmentOrder, request()->user()->id);

        return redirect()->route('employment-orders.index')->with('success', 'حکم کارگزینی تایید شد.');
    }
}
