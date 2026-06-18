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
                <div class="summary-value">{{ number_format($summary['debit']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">جمع بستانکار (ریال)</div>
                <div class="summary-value">{{ number_format($summary['credit']) }}</div>
            </div>
        </section>

        <div class="print-note">
            مانده حساب:
            <strong>{{ number_format(abs($summary['balance'])) }} {{ $summary['balance_type'] }}</strong>
        </div>

        <table style="margin-bottom: 18px;">
            <thead>
            <tr>
                <th>تاریخ</th>
                <th>شماره سند</th>
                <th>حساب</th>
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
                    <td>{{ $line['document_number'] ?: '-' }}</td>
                    <td>{{ $line['account'] ?: '-' }}</td>
                    <td>{{ $line['description'] ?: '-' }}</td>
                    <td class="text-left" dir="ltr">{{ $line['debit'] ? number_format($line['debit']) : '-' }}</td>
                    <td class="text-left" dir="ltr">{{ $line['credit'] ? number_format($line['credit']) : '-' }}</td>
                    <td class="text-left" dir="ltr">{{ number_format($line['running_balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">گردشی برای نمایش وجود ندارد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    @empty
        <div class="print-note">صورتحسابی برای نمایش وجود ندارد.</div>
    @endforelse
@endsection
