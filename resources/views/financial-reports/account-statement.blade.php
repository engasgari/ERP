<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">گردش حساب {{ $account->title }}</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">{{ $account->code }} - {{ $account->title }}</h2>
                <div class="text-sm text-slate-500">سطح: {{ $account->level }} | ماهیت: {{ $account->nature }}</div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('financial-reports.account-statement.print', array_merge(['account' => $account->id], request()->query())) }}" target="_blank" data-no-spa class="erp-action-btn erp-action-detail">چاپ</a>
                <a href="{{ route('chart-accounts.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            </div>
        </div>

        <form method="get" class="erp-ui-filter-bar">
            <div class="erp-filter-row erp-filter-row-3">
                <label class="erp-filter-field">از تاریخ
                    <input name="date_from" inputmode="numeric" dir="ltr" placeholder="1405/01/01" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}">
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <input name="date_to" inputmode="numeric" dir="ltr" placeholder="1405/12/29" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}">
                </label>
                <x-filter-actions :reset-route="route('financial-reports.account-statement', $account)" />
            </div>
        </form>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">جمع بدهکار (ریال)</div><div class="font-bold">{{ number_format($summary['debit']) }}</div></div>
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">جمع بستانکار (ریال)</div><div class="font-bold">{{ number_format($summary['credit']) }}</div></div>
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">مانده</div><div class="font-bold">{{ number_format(abs($summary['balance'])) }}</div></div>
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">ماهیت مانده</div><div class="font-bold">{{ $summary['balance_type'] }}</div></div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
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
                        <td>
                            @if(data_get($line, 'document'))
                                <a class="erp-modal-trigger" href="{{ route('accounting-documents.show', data_get($line, 'document')) }}">{{ data_get($line, 'document.number') }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ data_get($line, 'account.code') }} - {{ data_get($line, 'account.title') }}</td>
                        <td>{{ data_get($line, 'party.name', '-') }}</td>
                        <td>{{ data_get($line, 'project.name', '-') }}</td>
                        <td>{{ data_get($line, 'description') ?: data_get($line, 'document.description', '-') }}</td>
                        <td>{{ (float) data_get($line, 'debit') ? number_format((float) data_get($line, 'debit')) : '-' }}</td>
                        <td>{{ (float) data_get($line, 'credit') ? number_format((float) data_get($line, 'credit')) : '-' }}</td>
                        <td>{{ number_format((float) data_get($line, 'running_balance')) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-slate-500 py-6">گردشی برای این سرفصل وجود ندارد.</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="6" class="font-bold">جمع</td>
                    <td class="font-bold">{{ number_format($summary['debit']) }}</td>
                    <td class="font-bold">{{ number_format($summary['credit']) }}</td>
                    <td class="font-bold">{{ number_format($summary['balance']) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
