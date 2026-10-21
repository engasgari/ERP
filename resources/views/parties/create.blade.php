@php
    $pageTitle = $pageTitle ?? ((isset($party) && $party->exists) ? 'ویرایش شخص/شرکت' : 'تعریف شخص/شرکت');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $pageTitle }}</h2>
    </x-slot>

    @include('parties.partials.form', ['embedded' => false])
</x-app-layout>
