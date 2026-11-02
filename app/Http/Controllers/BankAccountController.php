<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Party;
use App\Models\Project;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        return view('bank-accounts.index');
    }

    public function statement(BankAccount $bankAccount, Request $request, FinancialReportService $reports)
    {
        // Capture the path id up front — never rebuild this URL via route($model)
        // after report queries (UrlGenerationException if route-key is unavailable).
        $bankAccountId = (int) ($bankAccount->getKey() ?: $request->route('bankAccount'));
        if ($bankAccountId <= 0) {
            abort(404);
        }

        $bankAccount->loadMissing('account', 'detailAccount');

        $filters = $this->statementFilters($request, $bankAccount);
        $filters['bank_account_id'] = $bankAccountId;
        $report = $reports->bankTransactions($bankAccount, $filters);
        $statementUrl = url('/bank-accounts/'.$bankAccountId.'/statement');

        return view('bank-accounts.statement', [
            'bankAccount' => $bankAccount,
            'statementUrl' => $statementUrl,
            'report' => $report,
            'filters' => $filters,
            'parties' => Party::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    /**
     * Keep statement filters local to this page so invalid query strings never
     * trigger FormRequest redirect/404 loops.
     *
     * @return array<string, mixed>
     */
    private function statementFilters(Request $request, BankAccount $bankAccount): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (filled($dateFrom)) {
            $dateFrom = jalaliToGregorianDate($dateFrom) ?: null;
        }

        if (filled($dateTo)) {
            $dateTo = jalaliToGregorianDate($dateTo) ?: null;
        }

        $partyId = $request->input('party_id');
        $projectId = $request->input('project_id');

        return [
            'bank_account_id' => (int) $bankAccount->getKey(),
            'date_from' => $dateFrom ?: null,
            'date_to' => $dateTo ?: null,
            'search' => filled($request->input('search')) ? trim((string) $request->input('search')) : null,
            'party_id' => filled($partyId) && ctype_digit((string) $partyId) ? (int) $partyId : null,
            'project_id' => filled($projectId) && ctype_digit((string) $projectId) ? (int) $projectId : null,
        ];
    }
}
