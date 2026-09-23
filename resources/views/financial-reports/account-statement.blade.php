<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">گردش حساب {{ $account->title }}</h2>
    </x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-lg font-bold">{{ chartAccountDisplayLabel($account) }}</h2>
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
                    <x-erp.ui.jalali-date-input name="date_from" :value="request('date_from') ? jalaliDateInputValue(request('date_from')) : ''" placeholder="1405/01/01" class="w-full" />
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <x-erp.ui.jalali-date-input name="date_to" :value="request('date_to') ? jalaliDateInputValue(request('date_to')) : ''" placeholder="1405/12/29" class="w-full" />
                </label>
                <x-filter-actions :reset-route="route('financial-reports.account-statement', $account)" />
            </div>
        </form>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">جمع بدهکار (ریال)</div><div class="font-bold">{{ formatMoney($summary['debit']) }}</div></div>
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">جمع بستانکار (ریال)</div><div class="font-bold">{{ formatMoney($summary['credit']) }}</div></div>
            <div class="bg-gray-50 rounded-lg p-3"><div class="text-xs text-gray-500">مانده</div><div class="font-bold">{{ formatMoney(abs($summary['balance'])) }}</div></div>
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
                        <td>{{ chartAccountDisplayLabel(data_get($line, 'account')) }}</td>
                        <td>{{ data_get($line, 'party.name', '-') }}</td>
                        <td>{{ data_get($line, 'project.name', '-') }}</td>
                        <td>{{ data_get($line, 'description') ?: data_get($line, 'document.description', '-') }}</td>
                        <td>{{ (float) data_get($line, 'debit') ? formatMoney((float) data_get($line, 'debit')) : '-' }}</td>
                        <td>{{ (float) data_get($line, 'credit') ? formatMoney((float) data_get($line, 'credit')) : '-' }}</td>
                        <td>{{ formatMoney((float) data_get($line, 'running_balance')) }}</td>
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
                    <td class="font-bold">{{ formatMoney($summary['debit']) }}</td>
                    <td class="font-bold">{{ formatMoney($summary['credit']) }}</td>
                    <td class="font-bold">{{ formatMoney($summary['balance']) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
