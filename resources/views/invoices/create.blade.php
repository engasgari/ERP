@php
    $pageTitle = match (true) {
        ($documentType ?? 'invoice') === 'proforma' => 'صدور پیش‌فاکتور فروش',
        ($documentType ?? 'invoice') === 'return' && ($direction ?? 'sale') === 'sale' => 'صدور برگشت از فروش',
        ($documentType ?? 'invoice') === 'return' => 'صدور برگشت از خرید',
        ($direction ?? 'sale') === 'sale' => 'صدور فاکتور فروش',
        default => 'صدور فاکتور خرید',
    };
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $pageTitle }}</h2>
    </x-slot>

    @include('invoices.partials.editor', ['embedded' => false])
</x-app-layout>
