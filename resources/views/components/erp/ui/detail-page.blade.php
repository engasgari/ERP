@props([
    'title',
    'description' => '',
    'route' => null,
    'actions' => [],
])
<x-erp.ui.panel>
    <x-erp.ui.page-header :title="$title" :description="$description" :actions="$actions">
        @isset($toolbar)
            <div class="flex flex-wrap gap-2 w-full md:w-auto md:justify-end">{{ $toolbar }}</div>
        @endisset
    </x-erp.ui.page-header>

    @include('livewire.partials.flash')

    {{ $slot }}
</x-erp.ui.panel>
