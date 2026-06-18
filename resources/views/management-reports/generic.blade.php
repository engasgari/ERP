<x-erp.ui.page-shell :title="$title">
    <x-erp.ui.panel>
        <x-erp.ui.page-header title="{{ $title }}" />

        <x-erp.ui.data-table :headers="$headers" :colspan="count($headers)" empty-message="داده‌ای برای نمایش وجود ندارد.">
            @forelse($rows as $row)
                <tr>
                    @foreach($mapper($row) as $cell)
                        <td>{!! ($raw ?? false) ? $cell : e($cell) !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}" class="px-3 py-8 text-center text-slate-500">داده‌ای برای نمایش وجود ندارد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>

        <div class="mt-4">{{ $rows->links() }}</div>
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
