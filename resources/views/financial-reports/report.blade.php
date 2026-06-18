<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $reportTitle }}</h2>
    </x-slot>

    @include('financial-reports.partials.report-shell', ['printMode' => false])
</x-app-layout>
