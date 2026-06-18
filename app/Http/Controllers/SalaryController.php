<?php

namespace App\Http\Controllers;

use App\Models\Salary;
use App\Models\Payment;
use App\Models\Employee;
use App\Models\WorkLog;
use App\Models\AccountingDocument;
use App\Models\EmployeeTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Services\EmployeeTransactionService;
use App\Services\AccountingPostingService;
use Illuminate\Support\Facades\DB;

class SalaryController extends Controller
{

    public function index(Request $request)
    {
        $query = Salary::with(['employee', 'payments', 'accountingDocument']);
        $this->scopeSalaryQuery($query, $request->user(), 'salaries.view');

        if ($request->has('employee') && !empty($request->employee)) {
            $query->where('employee_id', $request->employee);
        }

        if ($request->has('year') && !empty($request->year)) {
            $query->where('year', $request->year);
        }

        if ($request->has('month') && !empty($request->month)) {
            $query->where('month', $request->month);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $statsQuery = clone $query;

        $query->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('created_at', 'desc');

        $salaries = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'paid' => (clone $statsQuery)->where('status', 'paid')->count(),
            'partial' => (clone $statsQuery)->where('status', 'partial')->count(),
            'pending' => (clone $statsQuery)->whereIn('status', ['calculated', 'draft'])->count()
        ];

        $employees = $this->employeeQueryForUser($request->user(), 'salaries.view')->orderBy('first_name')->get();

        return view('salaries.index', compact('salaries', 'stats', 'employees'));
    }


    public function create()
    {
        return redirect()->route('payroll.periods.index');
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:1400|max:1500',
            'month' => 'required|integer|min:1|max:12',
            'overtime_rate_multiplier' => 'required|numeric|min:1|max:3',
        ]);

        $year = $request->year;
        $month = $request->month;
        $overtimeMultiplier = $request->overtime_rate_multiplier;

        $dateRange = getPersianMonthRange($year, $month);
//            return $dateRange;
        $workLogs = WorkLog::whereBetween('work_date', [$dateRange['start'], $dateRange['end']])
            ->whereIn('employee_id', $request->user()->accessibleEmployeeIds('salaries.manage'))
            ->with('employee')
            ->get();

        if ($workLogs->isEmpty()) {
            return back()->with('error',
                "هیچ کارکردی برای ماه " . getPersianMonthName($month) . " $year پیدا نشد. " .
                "بازه جستجو: {$dateRange['start_jalali']} تا {$dateRange['end_jalali']}"
            );
        }

        $calculatedSalaries = [];
        $totalWorkHours = 0;
        $totalOvertimeHours = 0;
        $totalNetSalary = 0;

        foreach ($workLogs->groupBy('employee_id') as $employeeId => $logs) {
            $employee = $logs->first()->employee;

            $totalHours = $logs->sum('hours');

            $overtimeHours = 0;
            foreach ($logs as $log) {
                if ($log->hours > 20) {
                    $overtimeHours += $log->hours - 20;
                }
            }

            $hourlyRate = $employee->hourly_rate;
            $baseSalary = $totalHours * $hourlyRate;
            $overtimeRate = $hourlyRate * $overtimeMultiplier;
            $overtimeSalary = $overtimeHours * $overtimeRate;
            $netSalary = $baseSalary + $overtimeSalary;

            $totalWorkHours += $totalHours;
            $totalOvertimeHours += $overtimeHours;
            $totalNetSalary += $netSalary;

            $calculatedSalaries[] = [
                'employee' => $employee,
                'total_hours' => $totalHours,
                'hourly_rate' => $hourlyRate,
                'base_salary' => $baseSalary,
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'overtime_salary' => $overtimeSalary,
                'net_salary' => $netSalary,
            ];
        }

        $monthName = getPersianMonthName($month);

        return view('salaries.calculate', compact(
            'calculatedSalaries',
            'year',
            'month',
            'monthName',
            'totalWorkHours',
            'totalOvertimeHours',
            'totalNetSalary',
            'dateRange'
        ));
    }



    public function store(Request $request, AccountingPostingService $posting)
    {
        $request->validate([
            'salaries' => 'required|array',
            'salaries.*.employee_id' => 'required|exists:employees,id',
            'salaries.*.bonus' => 'nullable|numeric|min:0',
            'salaries.*.deduction' => 'nullable|numeric|min:0',
            'salaries.*.advance_payment' => 'nullable|numeric|min:0',
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $year = $request->year;
        $month = $request->month;
        $createdCount = 0;

        DB::transaction(function () use ($request, $year, $month, $posting, &$createdCount) {
        foreach ($request->salaries as $salaryData) {
            abort_unless($request->user()->canAccessEmployee((int) $salaryData['employee_id'], 'salaries.manage'), 403);
            if ($salaryData['base_salary'] > 0) {
                $netSalary = (float) $salaryData['net_salary'];
                $bonus = (float) ($salaryData['bonus'] ?? 0);
                $benefits = (float) ($salaryData['benefits'] ?? 0);
                $deduction = (float) ($salaryData['deduction'] ?? 0);
                $insuranceAmount = (float) ($salaryData['insurance_amount'] ?? 0);
                $taxAmount = (float) ($salaryData['tax_amount'] ?? 0);
                $loanAmount = (float) ($salaryData['loan_amount'] ?? 0);
                $penaltyAmount = (float) ($salaryData['penalty_amount'] ?? 0);
                $advancePayment = (float) ($salaryData['advance_payment'] ?? 0);
                $grossSalary = $netSalary + $bonus + $benefits;
                $totalDeductions = $deduction + $insuranceAmount + $taxAmount + $loanAmount + $penaltyAmount + $advancePayment;
                $finalSalary = $grossSalary - $totalDeductions;

                $salary = Salary::firstOrNew(
                    [
                        'employee_id' => $salaryData['employee_id'],
                        'year' => $year,
                        'month' => $month,
                    ]
                );

                if ($salary->exists) {
                    $this->deleteSalaryAccountingDocument($salary);
                    $this->deleteSalaryTransactions($salary);
                }

                $salary->fill([
                    'total_hours' => $salaryData['total_hours'],
                    'hourly_rate' => $salaryData['hourly_rate'],
                    'base_salary' => $salaryData['base_salary'],
                    'overtime_hours' => $salaryData['overtime_hours'],
                    'overtime_rate' => $salaryData['overtime_rate'],
                    'overtime_salary' => $salaryData['overtime_salary'],
                    'bonus' => $bonus,
                    'benefits' => $benefits,
                    'gross_salary' => $grossSalary,
                    'deduction' => $deduction,
                    'insurance_amount' => $insuranceAmount,
                    'tax_amount' => $taxAmount,
                    'loan_amount' => $loanAmount,
                    'penalty_amount' => $penaltyAmount,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'advance_payment' => $advancePayment,
                    'final_salary' => $finalSalary,
                    'status' => 'calculated',
                    'accounting_document_id' => null,
                    'posted_at' => null,
                ])->save();

                EmployeeTransactionService::createSalaryTransaction($salary);

                if ($bonus > 0) {
                    EmployeeTransactionService::createBonusTransaction($salary);
                }

                if ($totalDeductions > 0) {
                    EmployeeTransactionService::createDeductionTransaction($salary);
                }

                if ($advancePayment > 0) {
                    EmployeeTransactionService::createAdvanceTransaction($salary);
                }

                $posting->fromSalary($salary->refresh(), $request->user()->id);
                $createdCount++;
            }
        }
        });

        $message = $createdCount > 0
            ? "حقوق‌ها برای {$createdCount} پرسنل با موفقیت محاسبه و ذخیره شدند."
            : "هیچ داده‌ای برای ذخیره وجود ندارد.";

        return redirect()->route('salaries.index')
            ->with('success', $message);
    }

    public function show(Salary $salary)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.view'), 403);
        $salary->load(['employee', 'payments', 'accountingDocument']);

        return view('salaries.show', compact('salary'));
    }

    public function edit(Salary $salary)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.manage'), 403);
        $employees = $this->employeeQueryForUser(request()->user(), 'salaries.manage')->orderBy('first_name')->get();
        $salary->load('employee');

        return view('salaries.edit', compact('salary', 'employees'));
    }

    public function update(Request $request, Salary $salary)
    {
        abort_unless($request->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.manage'), 403);
        abort_unless($request->user()->canAccessEmployee((int) $request->input('employee_id'), 'salaries.manage'), 403);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'year' => 'required|integer|min:1400|max:1500',
            'month' => 'required|integer|min:1|max:12',
            'total_hours' => 'required|numeric|min:0',
            'hourly_rate' => 'required|numeric|min:0',
            'base_salary' => 'required|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:0',
            'overtime_salary' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'benefits' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
            'insurance_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'loan_amount' => 'nullable|numeric|min:0',
            'penalty_amount' => 'nullable|numeric|min:0',
            'advance_payment' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,calculated,partial,paid',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['overtime_hours'] = $validated['overtime_hours'] ?? 0;
        $validated['overtime_rate'] = $validated['overtime_rate'] ?? 0;
        $validated['overtime_salary'] = $validated['overtime_salary'] ?? 0;
        $validated['bonus'] = $validated['bonus'] ?? 0;
        $validated['benefits'] = $validated['benefits'] ?? 0;
        $validated['deduction'] = $validated['deduction'] ?? 0;
        $validated['insurance_amount'] = $validated['insurance_amount'] ?? 0;
        $validated['tax_amount'] = $validated['tax_amount'] ?? 0;
        $validated['loan_amount'] = $validated['loan_amount'] ?? 0;
        $validated['penalty_amount'] = $validated['penalty_amount'] ?? 0;
        $validated['advance_payment'] = $validated['advance_payment'] ?? 0;
        $validated['net_salary'] = $validated['base_salary'] + $validated['overtime_salary'];
        $validated['gross_salary'] = $validated['net_salary'] + $validated['bonus'] + $validated['benefits'];
        $validated['total_deductions'] = $validated['deduction'] + $validated['advance_payment'] + $validated['insurance_amount'] + $validated['tax_amount'] + $validated['loan_amount'] + $validated['penalty_amount'];
        $validated['final_salary'] = $validated['gross_salary'] - $validated['total_deductions'];

        DB::transaction(function () use ($salary, $validated, $request) {
            $this->deleteSalaryAccountingDocument($salary);
            $this->deleteSalaryTransactions($salary);
            $salary->update($validated + ['accounting_document_id' => null, 'posted_at' => null]);
            EmployeeTransactionService::createSalaryTransaction($salary->refresh());
            if ((float) $salary->bonus > 0 || (float) $salary->benefits > 0) {
                EmployeeTransactionService::createBonusTransaction($salary);
            }
            if ((float) $salary->total_deductions > 0) {
                EmployeeTransactionService::createDeductionTransaction($salary);
            }
            if ((float) $salary->advance_payment > 0) {
                EmployeeTransactionService::createAdvanceTransaction($salary);
            }
            app(AccountingPostingService::class)->fromSalary($salary, $request->user()->id);
        });

        return redirect()->route('salaries.index')
            ->with('success', 'حقوق با موفقیت ویرایش شد.');
    }

    public function storePayment(Request $request, Salary $salary)
    {
        abort_unless($request->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.manage'), 403);

        $request->merge([
            'payment_date' => jalaliToGregorianDate($request->input('payment_date')),
        ]);

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank,card',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $remaining = $salary->final_salary - $salary->payments->sum('amount');
        if ($request->amount > $remaining) {
            return back()->with('error', 'مبلغ پرداختی بیشتر از مانده حقوق است.');
        }

        $payment = Payment::create([
            'salary_id' => $salary->id,
            'employee_id' => $salary->employee_id,
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'description' => $request->description,
        ]);

        EmployeeTransactionService::createPaymentTransaction($payment);

        $paidAmount = $salary->payments()->sum('amount');
        if ($paidAmount >= $salary->final_salary) {
            $salary->update(['status' => 'paid']);
        } elseif ($paidAmount > 0) {
            $salary->update(['status' => 'partial']);
        }

        return back()->with('success', 'پرداخت با موفقیت ثبت شد.');
    }


    public function bulkDeleteForm()
    {
        $salaries = Salary::with('employee')
            ->whereIn('employee_id', request()->user()->accessibleEmployeeIds('salaries.manage'))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->groupBy(function($salary) {
                return $salary->year . '-' . $salary->month;
            });
        return view('salaries.bulk-delete', compact('salaries'));
    }

    public function destroy(Request $request,$id)
    {
        if($id == 'destroy-multiple'){
            return $this->destroyMultiple($request);
        }else{
            try {
                $salary = Salary::findOrFail($id);
                abort_unless($request->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.manage'), 403);

                DB::transaction(fn () => $this->deleteSalaryWithRelatedDocuments($salary));

                // salary itself was deleted by deleteSalaryWithRelatedDocuments.

                return redirect()->route('salaries.index')
                    ->with('success', 'محاسبه حقوق با موفقیت حذف شد.');

            } catch (\Exception $e) {
                return redirect()->back()
                    ->with('error', 'خطا در حذف محاسبه حقوق: ' . $e->getMessage());
            }
        }

    }

    public function destroyMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:salaries,id',
        ]);

        $salaryIds = collect($request->ids)->map(fn ($id) => (int) $id)->unique()->values();
        $salaries = Salary::whereIn('id', $salaryIds)->get();

        foreach ($salaries as $salary) {
            abort_unless($request->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.manage'), 403);
        }

        DB::transaction(function () use ($salaries) {
            foreach ($salaries as $salary) {
                $this->deleteSalaryWithRelatedDocuments($salary);
            }
        });

        return redirect()->route('salaries.index')
            ->with('success', $salaries->count() . ' محاسبه حقوق حذف شد.');
    }
    public function financialReport(Request $request)
    {
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $request->merge([
                'start_date' => jalaliToGregorianDate($request->input('start_date')),
                'end_date' => jalaliToGregorianDate($request->input('end_date')),
            ]);
        }

        $query = $this->employeeQueryForUser($request->user(), 'salaries.view')
            ->with(['transactions' => function($query) use ($request) {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
            }
        }]);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $employees = $query->get()->map(function($employee) use ($request) {
                $employee->filtered_transactions = $employee->transactions->filter(function($transaction) use ($request) {
                    return $transaction->transaction_date >= $request->start_date &&
                        $transaction->transaction_date <= $request->end_date;
                });
                return $employee;
            });
        } else {
            $employees = $query->get();
        }

        $report = [];
        foreach ($employees as $employee) {
            $transactions = isset($employee->filtered_transactions) ?
                $employee->filtered_transactions :
                $employee->transactions;

            $debit = $transactions->where('type', 'debit')->sum('amount');
            $credit = $transactions->where('type', 'credit')->sum('amount');
            $balance = $credit - $debit;

            $report[] = [
                'employee' => $employee,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'transactions_count' => $transactions->count()
            ];
        }

        return view('salaries.financial-report', compact('report'));
    }

    public function employeeStatement($employeeId)
    {
        abort_unless(request()->user()->canAccessEmployee((int) $employeeId, 'salaries.view'), 403);
        return view('salaries.employee-statement', $this->employeeStatementData((int) $employeeId));
    }

    public function printSalarySlip($id)
    {
        $salary = Salary::with(['employee', 'payments'])->findOrFail($id);
        abort_unless(request()->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.view'), 403);

        $totalPayments = $salary->payments->sum('amount');
        $remaining = $salary->final_salary - $totalPayments;

        return view('salaries.print-slip', compact('salary', 'totalPayments', 'remaining'));
    }

    private function employeeStatementData(int $employeeId): array
    {
        $employee = Employee::findOrFail($employeeId);

        $transactions = $employee->transactions()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $debitTotal = $employee->transactions()->debit()->sum('amount');
        $creditTotal = $employee->transactions()->credit()->sum('amount');
        $balance = $creditTotal - $debitTotal;

        return compact('employee', 'transactions', 'debitTotal', 'creditTotal', 'balance');
    }

    public function downloadSalarySlip($id)
    {
        $salary = Salary::with(['employee', 'payments'])->findOrFail($id);
        abort_unless(request()->user()->canAccessEmployee((int) $salary->employee_id, 'salaries.view'), 403);
        $totalPayments = $salary->payments->sum('amount');
        $remaining = $salary->final_salary - $totalPayments;

        $pdf = Pdf::loadView('salaries.print-slip', [
            'salary' => $salary,
            'totalPayments' => $totalPayments,
            'remaining' => $remaining,
            'download' => true
        ]);

        $filename = "فیش_حقوقی_{$salary->employee->full_name}_{$salary->year}_{$salary->month}.pdf";

        return $pdf->download($filename);
    }

    private function scopeSalaryQuery($query, $user, string $scope): void
    {
        if (!$user->isAdmin()) {
            $query->whereIn('employee_id', $user->accessibleEmployeeIds($scope));
        }
    }

    private function employeeQueryForUser($user, string $scope)
    {
        $query = Employee::query();

        if (!$user->isAdmin()) {
            $query->whereIn('id', $user->accessibleEmployeeIds($scope));
        }

        return $query;
    }

    private function deleteSalaryWithRelatedDocuments(Salary $salary): void
    {
        $paymentIds = $salary->payments()->pluck('id');

        EmployeeTransaction::where('reference_type', 'payment')
            ->whereIn('reference_id', $paymentIds)
            ->delete();

        $salary->payments()->delete();
        $this->deleteSalaryTransactions($salary);
        $this->deleteSalaryAccountingDocument($salary);
        $salary->delete();
    }

    private function deleteSalaryTransactions(Salary $salary): void
    {
        EmployeeTransaction::whereIn('reference_type', ['salary', 'bonus', 'deduction', 'advance'])
            ->where('reference_id', $salary->id)
            ->delete();
    }

    private function deleteSalaryAccountingDocument(Salary $salary): void
    {
        $document = $salary->accountingDocument
            ?: AccountingDocument::where('source_type', Salary::class)
                ->where('source_id', $salary->id)
                ->first();

        if (! $document) {
            return;
        }

        $salary->forceFill([
            'accounting_document_id' => null,
            'posted_at' => null,
        ])->save();

        $document->lines()->delete();
        $document->delete();
    }
}


