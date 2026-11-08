@extends('management-reports.print.layout')

@section('content')
    @if(! empty($company?->name))
        <div class="print-note">{{ $company->name }}</div>
    @endif

    <div class="print-note">
        {{ $bankAccount->code }} — {{ $bankAccount->bank_name }}
        @if($bankAccount->account_number)
            | شماره حساب: {{ $bankAccount->account_number }}
        @endif
        @if($bankAccount->detailAccount)
            | تفصیل: {{ $bankAccount->detailAccount->code }} - {{ $bankAccount->detailAccount->title }}
        @endif
    </div>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">مانده افتتاحیه</div>
            <div class="summary-value">{{ formatMoney((float) ($report['summary']['opening_balance'] ?? 0)) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع بدهکار دوره</div>
            <div class="summary-value">{{ formatMoney((float) ($report['summary']['period_debit'] ?? 0)) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">جمع بستانکار دوره</div>
            <div class="summary-value">{{ formatMoney((float) ($report['summary']['period_credit'] ?? 0)) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">مانده نهایی بانک</div>
            <div class="summary-value">{{ formatMoney((float) ($report['summary']['closing_balance'] ?? 0)) }}</div>
        </div>
    </section>

    <table>
        <thead>
        <tr>
            <th>تاریخ</th>
            <th>شماره سند</th>
            <th>طرف حساب</th>
            <th>پروژه</th>
            <th>شرح</th>
            <th>بدهکار (ریال)</th>
            <th>بستانکار (ریال)</th>
            <th>مانده جاری</th>
        </tr>
        </thead>
        <tbody>
        @forelse(($report['sections'][0]['rows'] ?? []) as $row)
            <tr>
                <td>{{ $row['date'] ?? '-' }}</td>
                <td>{{ $row['document_number'] ?? '-' }}</td>
                <td>{{ $row['party'] ?? '-' }}</td>
                <td>{{ $row['project'] ?? '-' }}</td>
                <td>
                    {{ $row['description_primary'] ?? ($row['description'] ?? '-') }}
                    @if(! empty($row['description_secondary']))
                        <div style="color:#6b7280;font-size:11px;margin-top:2px;">{{ $row['description_secondary'] }}</div>
                    @endif
                </td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) ($row['debit'] ?? 0)) }}</td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) ($row['credit'] ?? 0)) }}</td>
                <td class="text-left" dir="ltr">{{ formatMoney((float) ($row['running_balance'] ?? 0)) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align:center;">تراکنشی برای این بانک در بازه انتخاب‌شده یافت نشد.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5">جمع دوره / مانده نهایی</td>
            <td class="text-left" dir="ltr">{{ formatMoney((float) ($report['summary']['period_debit'] ?? 0)) }}</td>
            <td class="text-left" dir="ltr">{{ formatMoney((float) ($report['summary']['period_credit'] ?? 0)) }}</td>
            <td class="text-left" dir="ltr">{{ formatMoney((float) ($report['summary']['closing_balance'] ?? 0)) }}</td>
        </tr>
        </tfoot>
    </table>
@endsection
