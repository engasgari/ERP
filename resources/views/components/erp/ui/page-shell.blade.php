@props(['title' => null])
<x-app-layout>
    @if($title)
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
        </x-slot>
    @endif

    <div class="py-6 md:py-10" dir="rtl">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
