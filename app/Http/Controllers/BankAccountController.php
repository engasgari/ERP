<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialReportRequest;
use App\Models\BankAccount;
use App\Models\ChartAccount;
use App\Models\Party;
use App\Models\Project;
use App\Services\FinancialReportService;

class BankAccountController extends Controller
{
    public function index()
    {
        return view('bank-accounts.index');
    }

    public function statement(BankAccount $bankAccount, FinancialReportRequest $request, FinancialReportService $reports)
    {
        $filters = $request->validated() + ['bank_account_id' => $bankAccount->id];
        $report = $reports->bankTransactions($bankAccount, $filters);

        return view('bank-accounts.statement', [
            'bankAccount' => $bankAccount,
            'report' => $report,
            'filters' => $filters,
            'parties' => Party::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'accounts' => ChartAccount::orderBy('code')->get(),
        ]);
    }
}
