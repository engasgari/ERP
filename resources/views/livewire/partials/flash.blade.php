@php
    $livewireFlashes = collect([
        'success' => session('success'),
        'danger' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ])->filter(fn ($message) => filled($message));

    $errorDetails = session('error_details', []);
@endphp

@foreach($livewireFlashes as $tone => $message)
    <div
        wire:ignore
        class="erp-livewire-flash-bridge"
        data-erp-livewire-flash-tone="{{ $tone }}"
        data-erp-livewire-flash-message='@json($message, JSON_UNESCAPED_UNICODE)'
        @if($tone === 'danger' && ! empty($errorDetails))
            data-erp-livewire-flash-details='@json($errorDetails, JSON_UNESCAPED_UNICODE)'
        @endif
        hidden
    ></div>
@endforeach
