@extends('management-reports.print.layout')

@section('content')
    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">پروژه</div>
            <div class="summary-value">{{ $project->name }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">کارفرما</div>
            <div class="summary-value">{{ $project->party?->name ?: '-' }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">مدیر پروژه</div>
            <div class="summary-value">{{ $project->manager?->name ?: '-' }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">شماره پروژه</div>
            <div class="summary-value">{{ $project->project_number ?: '-' }}</div>
        </div>
    </section>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">درآمد خالص (بدون ارزش افزوده)</div>
            <div class="summary-value">{{ formatMoney($summary['revenue']) }}</div>
            <div class="summary-label" style="margin-top:6px;">با ارزش افزوده</div>
            <div class="summary-value">{{ formatMoney($summary['gross_revenue']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع هزینه (بدون ارزش افزوده)</div>
            <div class="summary-value">{{ formatMoney($summary['total_cost_net']) }}</div>
            <div class="summary-label" style="margin-top:6px;">با ارزش افزوده</div>
            <div class="summary-value">{{ formatMoney($summary['total_cost']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">سود / زیان خالص</div>
            <div class="summary-value">{{ formatMoney($summary['profit_net'] ?? $summary['gross_profit']) }}</div>
            <div class="summary-label" style="margin-top:6px;">با ارزش افزوده</div>
            <div class="summary-value">{{ formatMoney($summary['profit_gross'] ?? $summary['gross_profit']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">حاشیه سود</div>
            <div class="summary-value">{{ formatMoney($summary['profit_margin'], 2) }}%</div>
        </div>
    </section>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>شرح درآمد</th>
            <th>مبلغ (ریال)</th>
        </tr>
        </thead>
        <tbody>
        <tr><td>درآمد فاکتور فروش (ناخالص)</td><td class="text-left" dir="ltr">{{ formatMoney($summary['invoice_gross_revenue']) }}</td></tr>
        <tr><td>مالیات ارزش افزوده دریافتی (کسر)</td><td class="text-left" dir="ltr">-{{ formatMoney($summary['vat_collected']) }}</td></tr>
        @if(($summary['financial_revenue'] ?? 0) > 0)
        <tr><td>درآمد مالی متفرقه</td><td class="text-left" dir="ltr">{{ formatMoney($summary['financial_revenue']) }}</td></tr>
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td>درآمد خالص پروژه</td>
            <td class="text-left" dir="ltr">{{ formatMoney($summary['revenue']) }}</td>
        </tr>
        </tfoot>
    </table>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>شرح هزینه</th>
            <th>مبلغ (ریال)</th>
        </tr>
        </thead>
        <tbody>
        <tr><td>مواد مصرفی</td><td class="text-left" dir="ltr">{{ formatMoney($summary['material_cost']) }}</td></tr>
        <tr><td>دستمزد مستقیم</td><td class="text-left" dir="ltr">{{ formatMoney($summary['labor_cost']) }}</td></tr>
        <tr><td>خدمات و خرید مستقیم (بدون ارزش افزوده)</td><td class="text-left" dir="ltr">{{ formatMoney($summary['service_cost_net']) }}</td></tr>
        <tr><td>خدمات و خرید مستقیم (با ارزش افزوده)</td><td class="text-left" dir="ltr">{{ formatMoney($summary['service_cost']) }}</td></tr>
        <tr><td>هزینه‌های ثبت‌شده پروژه</td><td class="text-left" dir="ltr">{{ formatMoney($summary['registered_expense_cost']) }}</td></tr>
        <tr><td>هزینه‌های حسابداری پروژه</td><td class="text-left" dir="ltr">{{ formatMoney($summary['ledger_expense_cost']) }}</td></tr>
        </tbody>
        <tfoot>
        <tr>
            <td>جمع بهای تمام‌شده (بدون ارزش افزوده)</td>
            <td class="text-left" dir="ltr">{{ formatMoney($summary['total_cost_net']) }}</td>
        </tr>
        <tr>
            <td>جمع بهای تمام‌شده (با ارزش افزوده)</td>
            <td class="text-left" dir="ltr">{{ formatMoney($summary['total_cost']) }}</td>
        </tr>
        </tfoot>
    </table>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>شاخص</th>
            <th>مقدار</th>
        </tr>
        </thead>
        <tbody>
        <tr><td>بودجه پروژه</td><td class="text-left" dir="ltr">{{ formatMoney($summary['budget']) }}</td></tr>
        <tr><td>مانده بودجه</td><td class="text-left" dir="ltr">{{ formatMoney($summary['budget_variance']) }}</td></tr>
        <tr><td>ساعت کار ثبت‌شده</td><td class="text-left" dir="ltr">{{ formatMoney($summary['work_hours'], 2) }}</td></tr>
        <tr><td>مغایرت مواد</td><td class="text-left" dir="ltr">{{ formatMoney($summary['material_variance']) }}</td></tr>
        </tbody>
    </table>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>فاکتورهای فروش پروژه</th>
            <th>مبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse($saleInvoices as $invoice)
            <tr>
                <td>{{ $invoice->number }} - {{ $invoice->party?->name ?: '-' }} - {{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) $invoice->total_amount) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">فاکتور فروش متصل به این پروژه ثبت نشده است.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>فاکتورهای خرید پروژه</th>
            <th>مبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse($purchaseInvoices as $invoice)
            <tr>
                <td>{{ $invoice->number }} - {{ $invoice->party?->name ?: '-' }} - {{ gregorianToJalaliDate($invoice->invoice_date) }}</td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) $invoice->total_amount) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">فاکتور خرید متصل به این پروژه ثبت نشده است.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>هزینه‌های ثبت‌شده پروژه</th>
            <th>مبلغ</th>
        </tr>
        </thead>
        <tbody>
        @forelse($registeredExpenses as $expense)
            <tr>
                <td>{{ gregorianToJalaliDate($expense->transaction_date) }} - {{ $expense->category ?: '-' }} - {{ $expense->description ?: '-' }}</td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) $expense->amount) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">هزینه ثبت‌شده‌ای برای این پروژه وجود ندارد.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <thead>
        <tr>
            <th>شماره سفارش</th>
            <th>محصول</th>
            <th>تعداد</th>
            <th>وضعیت</th>
        </tr>
        </thead>
        <tbody>
        @forelse($project->productionOrders as $order)
            <tr>
                <td>{{ $order->number }}</td>
                <td>{{ $order->item?->name ?: '-' }}</td>
                <td class="text-left" dir="ltr">{{ formatQuantity((float) $order->quantity) }}</td>
                <td>{{ $order->status_label }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">سفارشی تولیدی ثبت نشده است.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
