@props([
    'tone' => 'success',
    'title' => null,
    'message' => null,
    'details' => [],
])

@php
    $toneClass = match ($tone) {
        'danger', 'error' => 'is-danger',
        'warning' => 'is-warning',
        'info' => 'is-info',
        default => 'is-success',
    };

    $flashTone = match ($tone) {
        'danger', 'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        default => 'success',
    };

    $icon = match ($tone) {
        'danger', 'error' => '×',
        'warning' => '!',
        'info' => 'i',
        default => '✓',
    };

    $defaultTitle = match ($tone) {
        'danger', 'error' => 'خطا',
        'warning' => 'هشدار',
        'info' => 'اطلاع',
        default => 'انجام شد',
    };
@endphp

<div
    {{ $attributes->class(['erp-flash-toast', $toneClass]) }}
    data-erp-flash
    data-erp-flash-tone="{{ $flashTone }}"
    role="alert"
    aria-live="polite"
>
    <button type="button" class="erp-flash-close" data-erp-flash-close aria-label="بستن">×</button>
    <div class="erp-flash-icon" aria-hidden="true">{{ $icon }}</div>
    <div class="min-w-0">
        <div class="erp-flash-title">{{ $title ?: $defaultTitle }}</div>
        @if($message)
            <div class="erp-flash-message">{{ $message }}</div>
        @endif
        @if(! empty($details))
            <ul class="erp-flash-details">
                @foreach((array) $details as $detail)
                    <li>{{ $detail }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
