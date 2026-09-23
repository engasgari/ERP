@extends('management-reports.print.layout')

@section('content')
    @php
        $statusLabel = fn ($status) => $status === 'approved' ? 'تایید شده' : 'پیش‌نویس';
        $companyName = $company?->company_name ?: config('app.name', 'شرکت');
    @endphp

    <p class="print-note">
        شرکت: {{ $companyName }}
        @if(($filters['status'] ?? '') !== '')
            | وضعیت: {{ $statusLabel($filters['status']) }}
        @endif
        @if(($filters['search'] ?? '') !== '')
            | جستجو: {{ $filters['search'] }}
        @endif
    </p>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">تعداد احکام</div>
            <div class="summary-value">{{ count($rows) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">تایید شده</div>
            <div class="summary-value">{{ collect($rows)->where('status', 'approved')->count() }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع حقوق پایه</div>
            <div class="summary-value text-left">{{ formatMoney((float) collect($rows)->sum('base_salary')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع مشمول بیمه</div>
            <div class="summary-value text-left">{{ formatMoney((float) collect($rows)->sum(fn ($order) => $order->totalInsurableWage())) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ردیف</th>
                <th>شماره حکم</th>
                <th>پرسنل</th>
                <th>نوع حکم</th>
                <th>تاریخ اجرا</th>
                <th>گروه/رتبه/پایه</th>
                <th>پست</th>
                <th>حقوق پایه</th>
                <th>مشمول بیمه</th>
                <th>وضعیت</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $order)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $order->number }}</td>
                    <td>{{ $order->employee?->full_name ?: '-' }}</td>
                    <td>{{ $order->orderTypeLabel() }}</td>
                    <td>{{ formatJalaliDateSafe($order->effective_date) }}</td>
                    <td class="text-left" dir="ltr">{{ ($order->job_group ?: '-') . '/' . ($order->job_rank ?: '-') . '/' . ($order->job_base ?: '-') }}</td>
                    <td>{{ $order->position?->title ?: '-' }}</td>
                    <td class="text-left">{{ formatMoney((float) $order->base_salary) }}</td>
                    <td class="text-left">{{ formatMoney($order->totalInsurableWage()) }}</td>
                    <td>{{ $statusLabel($order->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center">رکوردی برای چاپ وجود ندارد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
