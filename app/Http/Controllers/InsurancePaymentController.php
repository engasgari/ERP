<?php

namespace App\Http\Controllers;

use App\Models\InsurancePayment;
use Illuminate\View\View;

class InsurancePaymentController extends Controller
{
    public function show(InsurancePayment $payment): View
    {
        $payment->load([
            'lines.period',
            'lines.liability',
            'bankAccount',
            'cashbox',
            'accountingDocument.lines',
            'creator',
            'audits.user',
        ]);

        return view('insurance.payments.show', compact('payment'));
    }
}
