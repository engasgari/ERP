<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ویرایش پرونده پرسنلی</h2>
    </x-slot>

    <livewire:employees.form :employee="$employee" />
</x-app-layout>
