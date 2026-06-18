@if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
        <div>{{ session('error') }}</div>
        @if(session('error_details'))
            <ul class="mt-2 list-disc pr-5 space-y-1">
                @foreach((array) session('error_details') as $detail)
                    <li>{{ $detail }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
