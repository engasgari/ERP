@php($isEdit = isset($item) && $item->exists)

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش کالا/خدمت' : 'تعریف کالا/خدمت' }}</h2>
    </x-slot>

    @include('items.partials.form')
</x-app-layout>
