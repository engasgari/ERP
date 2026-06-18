<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">گزارش سود و زیان</h2></x-slot>
    <div class="bg-white rounded-lg shadow-md p-6 grid md:grid-cols-3 gap-4">
        <div>درآمدها: {{ number_format($summary['revenue']) }}</div>
        <div>هزینه‌ها: {{ number_format($summary['expenses']) }}</div>
        <div>سود/زیان خالص: {{ number_format($summary['net_profit']) }}</div>
    </div>
</x-app-layout>
