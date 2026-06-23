<?php

namespace App\Http\Controllers;

use App\Services\ReportCenterService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCenterController extends Controller
{
    public function index(Request $request, ReportCenterService $reportCenter): View
    {
        $groups = $reportCenter->sections();

        return view('management-reports.index', [
            'groups' => $groups,
            'activeGroup' => $reportCenter->findSection($request->query('tab')),
        ]);
    }
}
