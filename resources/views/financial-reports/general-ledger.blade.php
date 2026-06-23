<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">دفتر کل</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6">
        <table class="erp-ui-data-table">
            <thead><tr><th>تاریخ</th><th>شماره سند</th><th>حساب</th><th>شخص/شرکت</th><th>شرح</th><th>بدهکار (ریال)</th><th>بستانکار (ریال)</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ gregorianToJalaliDate($row->document->document_date) }}</td>
                    <td>{{ $row->document->number }}</td>
                    <td>{{ chartAccountDisplayLabel($row->account) }}</td>
                    <td>{{ $row->party?->name ?: '-' }}</td>
                    <td>{{ $row->description }}</td>
                    <td>{{ number_format($row->debit) }}</td>
                    <td>{{ number_format($row->credit) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-500 py-6">ردیفی برای نمایش وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
