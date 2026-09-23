@props([
    'icon' => 'view',
    'label' => '',
    'tone' => 'default',
    'href' => null,
    'type' => 'button',
])

@php
    $toneClass = match ($tone) {
        'danger' => 'erp-row-action-btn--danger',
        'success' => 'erp-row-action-btn--success',
        default => '',
    };
    $classes = trim('erp-row-action-btn ' . $toneClass . ' ' . ($attributes->get('class') ?? ''));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes, 'title' => $label, 'aria-label' => $label]) }}>
        <x-erp.ui.action-icon :name="$icon" />
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes, 'title' => $label, 'aria-label' => $label]) }}>
        <x-erp.ui.action-icon :name="$icon" />
    </button>
@endIf
