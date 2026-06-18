@props(['headers' => [], 'emptyMessage' => 'رکوردی یافت نشد.', 'colspan' => null])
<div class="erp-table-wrap table-responsive overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'erp-ui-data-table w-full']) }}>
        <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
            @if(trim($slot) !== '')
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ $colspan ?: max(1, count($headers)) }}" class="text-center text-slate-500 py-6">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
