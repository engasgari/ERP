<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Payslip;
use App\Services\PayslipSnapshotService;
use App\Support\PersianPdf;

class PayslipController extends Controller
{
    public function print(Payslip $payslip, PayslipSnapshotService $snapshotService)
    {
        return view('payslips.print', $this->data($payslip, $snapshotService));
    }

    public function download(Payslip $payslip, PayslipSnapshotService $snapshotService)
    {
        $pdf = PersianPdf::loadView('payslips.print', $this->data($payslip, $snapshotService));

        return $pdf->download($payslip->number . '.pdf');
    }

    private function data(Payslip $payslip, PayslipSnapshotService $snapshotService): array
    {
        $payslip->load([
            'calculation.employee.party',
            'calculation.employee.organizationUnit',
            'calculation.employee.positionRecord',
            'calculation.period',
            'calculation.attendance',
            'calculation.lines',
            'period',
            'employee',
        ]);

        $calculation = $payslip->calculation;
        $attendance = $calculation?->attendance;
        $earningLines = $calculation?->lines
            ? $calculation->lines->where('type', 'earning')->filter(fn ($line) => (float) $line->amount != 0.0)->values()
            : collect();
        $deductionLines = $calculation?->lines
            ? $calculation->lines->where('type', 'deduction')->filter(fn ($line) => (float) $line->amount != 0.0)->values()
            : collect();

        $header = $snapshotService->headerForPrint($payslip);
        $company = CompanySetting::query()->first();

        return compact('payslip', 'calculation', 'attendance', 'earningLines', 'deductionLines', 'header') + [
            'companyName' => $company?->company_name ?: 'شرکت',
            'companyNationalId' => $company?->national_id ?? $company?->economic_code ?? null,
        ];
    }
}
