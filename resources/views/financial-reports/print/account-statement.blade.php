@extends('management-reports.print.layout')

@section('content')
    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">کد حساب</div>
            <div class="summary-value">{{ $account->code }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">عنوان حساب</div>
            <div class="summary-value">{{ $account->title }}</div>
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

    <table>
        <thead>
        <tr>
            <th>تاریخ</th>
            <th>شماره سند</th>
            <th>حساب</th>
            <th>شخص/شرکت</th>
            <th>پروژه</th>
            <th>شرح</th>
            <th>بدهکار (ریال)</th>
            <th>بستانکار (ریال)</th>
            <th>مانده جاری</th>
        </tr>
        </thead>
        <tbody>
        @forelse($summary['lines'] as $line)
            <tr>
                <td>{{ gregorianToJalaliDate(data_get($line, 'document.document_date')) ?: '-' }}</td>
                <td>{{ data_get($line, 'document.number', '-') }}</td>
                <td>{{ data_get($line, 'account.code') }} - {{ data_get($line, 'account.title') }}</td>
                <td>{{ data_get($line, 'party.name', '-') }}</td>
                <td>{{ data_get($line, 'project.name', '-') }}</td>
                <td>{{ data_get($line, 'description') ?: data_get($line, 'document.description', '-') }}</td>
                <td class="text-left" dir="ltr">{{ (float) data_get($line, 'debit') ? number_format((float) data_get($line, 'debit')) : '-' }}</td>
                <td class="text-left" dir="ltr">{{ (float) data_get($line, 'credit') ? number_format((float) data_get($line, 'credit')) : '-' }}</td>
                <td class="text-left" dir="ltr">{{ number_format((float) data_get($line, 'running_balance')) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="text-align: center;">گردشی برای این سرفصل وجود ندارد.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="6">جمع</td>
            <td class="text-left" dir="ltr">{{ number_format($summary['debit']) }}</td>
            <td class="text-left" dir="ltr">{{ number_format($summary['credit']) }}</td>
            <td class="text-left" dir="ltr">{{ number_format($summary['balance']) }}</td>
        </tr>
        </tfoot>
    </table>
@endsection
