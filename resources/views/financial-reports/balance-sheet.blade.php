<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">ترازنامه</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6 grid md:grid-cols-3 gap-4">
        <div>دارایی‌ها: {{ number_format($summary['assets']) }}</div>
        <div>بدهی‌ها: {{ number_format($summary['liabilities']) }}</div>
        <div>حقوق صاحبان سرمایه: {{ number_format($summary['equity']) }}</div>
    </div>
</x-app-layout>
