@props([
    'title',
    'description' => '',
    'actions' => [],
    'route' => null,
    'panelClass' => '',
    'showLoading' => true,
])
<x-erp.ui.panel :class="$panelClass" data-erp-preserve-scroll="1">
    <x-erp.ui.page-header :title="$title" :description="$description" :actions="$actions" />

    @include('livewire.partials.flash')

    @isset($filters)
        {{ $filters }}
    @endisset

    @if($showLoading)
        <div wire:loading.delay class="erp-page-loading">در حال به‌روزرسانی...</div>
    @endif

    {{ $slot }}
</x-erp.ui.panel>
