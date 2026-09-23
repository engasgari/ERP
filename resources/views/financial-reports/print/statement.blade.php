@extends('management-reports.print.layout')

@section('content')
    @forelse($summaries as $summary)
        <section class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">شخص / شرکت</div>
                <div class="summary-value">{{ $summary['party_name'] }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">کد</div>
                <div class="summary-value">{{ $summary['party_code'] }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">جمع بدهکار (ریال)</div>
                <div class="summary-value">{{ formatMoney($summary['debit']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">جمع بستانکار (ریال)</div>
                <div class="summary-value">{{ formatMoney($summary['credit']) }}</div>
            </div>
        </section>

        <div class="print-note">
            مانده حساب:
            <strong>{{ formatMoney(abs($summary['balance'])) }} {{ $summary['balance_type'] }}</strong>
        </div>

        <table style="margin-bottom: 18px;">
            <thead>
            <tr>
                <th>تاریخ</th>
                <th>نوع تراکنش</th>
                <th>شرح</th>
                <th>بدهکار (ریال)</th>
                <th>بستانکار (ریال)</th>
                <th>مانده جاری</th>
            </tr>
            </thead>
            <tbody>
            @forelse($summary['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] ?: '-' }}</td>
                    <td>{{ $line['transaction_type'] ?: '-' }}</td>
                    <td>{{ $line['description'] ?? '-' }}</td>
                    <td class="text-left" dir="ltr">{{ $line['debit'] ? formatMoney($line['debit']) : '-' }}</td>
                    <td class="text-left" dir="ltr">{{ $line['credit'] ? formatMoney($line['credit']) : '-' }}</td>
                    <td class="text-left" dir="ltr">{{ formatMoney($line['running_balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">گردشی برای نمایش وجود ندارد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    @empty
        <div class="print-note">صورتحسابی برای نمایش وجود ندارد.</div>
    @endforelse
@endsection
