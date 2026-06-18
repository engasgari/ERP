<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">گزارش سررسید مطالبات/بدهی‌ها</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6">
        <table class="erp-ui-data-table">
            <thead><tr><th>شخص/شرکت</th><th>مانده باز</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr><td>{{ $row['party']->name }}</td><td>{{ number_format($row['balance']) }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center text-slate-500 py-6">ردیفی برای نمایش وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
