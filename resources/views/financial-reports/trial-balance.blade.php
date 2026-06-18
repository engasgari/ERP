<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">تراز آزمایشی</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6">
        <table class="erp-ui-data-table">
            <thead><tr><th>حساب</th><th>گردش بدهکار (ریال)</th><th>گردش بستانکار (ریال)</th><th>مانده بدهکار (ریال)</th><th>مانده بستانکار (ریال)</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['account']?->code }} - {{ $row['account']?->title }}</td>
                    <td>{{ number_format($row['debit']) }}</td>
                    <td>{{ number_format($row['credit']) }}</td>
                    <td>{{ number_format($row['debit_balance']) }}</td>
                    <td>{{ number_format($row['credit_balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500 py-6">ردیفی برای نمایش وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
