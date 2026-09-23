@props([
    'title',
    'description' => '',
    'route' => null,
    'backUrl' => null,
])
@php
    $headerActions = $backUrl
        ? [['label' => 'بازگشت', 'url' => $backUrl, 'class' => 'erp-action-detail']]
        : [];
@endphp
<x-erp.ui.panel>
    <x-erp.ui.page-header :title="$title" :description="$description" :actions="$headerActions" />

    @include('livewire.partials.flash')

    {{ $slot }}
</x-erp.ui.panel>
