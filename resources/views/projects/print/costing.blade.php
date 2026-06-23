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
            <div class="summary-label">درآمد پروژه</div>
            <div class="summary-value">{{ number_format($summary['revenue']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع هزینه</div>
            <div class="summary-value">{{ number_format($summary['total_cost']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">سود / زیان</div>
            <div class="summary-value">{{ number_format($summary['gross_profit']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">حاشیه سود</div>
            <div class="summary-value">{{ number_format($summary['profit_margin'], 2) }}%</div>
        </div>
    </section>

    <table style="margin-bottom: 14px;">
        <thead>
        <tr>
            <th>شرح هزینه</th>
            <th>مبلغ (ریال)</th>
        </tr>
        </thead>
        <tbody>
        <tr><td>مواد مصرفی</td><td class="text-left" dir="ltr">{{ number_format($summary['material_cost']) }}</td></tr>
        <tr><td>دستمزد مستقیم</td><td class="text-left" dir="ltr">{{ number_format($summary['labor_cost']) }}</td></tr>
        <tr><td>خدمات و خرید مستقیم</td><td class="text-left" dir="ltr">{{ number_format($summary['service_cost']) }}</td></tr>
        </tbody>
        <tfoot>
        <tr>
            <td>جمع بهای تمام‌شده</td>
            <td class="text-left" dir="ltr">{{ number_format($summary['total_cost']) }}</td>
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
        <tr><td>بودجه پروژه</td><td class="text-left" dir="ltr">{{ number_format($summary['budget']) }}</td></tr>
        <tr><td>مانده بودجه</td><td class="text-left" dir="ltr">{{ number_format($summary['budget_variance']) }}</td></tr>
        <tr><td>ساعت کار ثبت‌شده</td><td class="text-left" dir="ltr">{{ number_format($summary['work_hours'], 2) }}</td></tr>
        <tr><td>مغایرت مواد</td><td class="text-left" dir="ltr">{{ number_format($summary['material_variance']) }}</td></tr>
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
                <td class="text-left" dir="ltr">{{ number_format((float) $invoice->total_amount) }}</td>
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
                <td class="text-left" dir="ltr">{{ number_format((float) $invoice->total_amount) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">فاکتور خرید متصل به این پروژه ثبت نشده است.</td>
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
                <td class="text-left" dir="ltr">{{ number_format((float) $order->quantity, 3) }}</td>
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
