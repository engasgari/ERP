@props(['title', 'summary' => [], 'headers' => [], 'rows' => [], 'printUrl' => null])
<x-erp.ui.panel>
    <x-erp.ui.page-header :title="$title" :actions="$printUrl ? [['label' => 'چاپ', 'url' => $printUrl, 'class' => 'erp-action-detail']] : []" />
    @if($summary)
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach($summary as $label => $value)
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                    <div class="mt-1 font-bold text-slate-800">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endif
    <x-erp.ui.data-table :headers="$headers">
        @forelse($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{!! $cell !!}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ max(1, count($headers)) }}" class="text-center text-slate-500 py-6">داده‌ای برای نمایش وجود ندارد.</td></tr>
        @endforelse
    </x-erp.ui.data-table>
</x-erp.ui.panel>
