<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;

class PayslipController extends Controller
{
    public function print(Payslip $payslip)
    {
        return view('payslips.print', $this->data($payslip));
    }

    public function download(Payslip $payslip)
    {
        $pdf = Pdf::loadView('payslips.print', $this->data($payslip));

        return $pdf->download($payslip->number . '.pdf');
    }

    private function data(Payslip $payslip): array
    {
        $payslip->load([
            'calculation.employee.party',
            'calculation.period',
            'calculation.attendance',
            'calculation.lines',
        ]);

        $calculation = $payslip->calculation;
        $attendance = $calculation->attendance;
        $earningLines = $calculation->lines->where('type', 'earning');
        $deductionLines = $calculation->lines->where('type', 'deduction');

        return compact('payslip', 'calculation', 'attendance', 'earningLines', 'deductionLines') + [
            'companyName' => optional(\App\Models\CompanySetting::first())->company_name,
        ];
    }
}
