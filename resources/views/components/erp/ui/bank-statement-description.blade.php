@props([
    'primary' => null,
    'secondary' => null,
    'description' => null,
])

@php
    $primaryText = trim((string) ($primary ?: $description ?: '-'));
    $secondaryText = trim((string) ($secondary ?? ''));
@endphp

<span class="bank-statement-description">
    <span class="bank-statement-description-primary">{{ $primaryText }}</span>
    @if($secondaryText !== '')
        <span class="bank-statement-description-separator"> — </span>
        <span class="bank-statement-description-secondary">{{ $secondaryText }}</span>
    @endif
</span>
