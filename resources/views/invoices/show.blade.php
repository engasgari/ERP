<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">پیش نمایش و چاپ فاکتور</h2>
    </x-slot>

    @include('invoices.partials.show', ['embedded' => false])
</x-app-layout>
