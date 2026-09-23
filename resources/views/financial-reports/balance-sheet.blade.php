<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">ترازنامه</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6 grid md:grid-cols-3 gap-4">
        <div>دارایی‌ها: {{ formatMoney($summary['assets']) }}</div>
        <div>بدهی‌ها: {{ formatMoney($summary['liabilities']) }}</div>
        <div>حقوق صاحبان سرمایه: {{ formatMoney($summary['equity']) }}</div>
    </div>
</x-app-layout>
