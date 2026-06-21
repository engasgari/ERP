@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm leading-6 text-red-800 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
