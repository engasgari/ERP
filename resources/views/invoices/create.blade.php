<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">صدور فاکتور</h2>
    </x-slot>

    @include('invoices.partials.editor', ['embedded' => false])
</x-app-layout>
