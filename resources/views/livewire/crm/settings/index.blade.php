<x-erp.ui.detail-page
    title="تنظیمات CRM"
    description="منابع سرنخ و مراحل پایپ‌لاین (فقط نمایش)."
    route="crm.settings.index"
>
    <h3 class="font-semibold mb-3">منابع سرنخ</h3>
    <x-erp.ui.data-table :headers="['کد', 'عنوان', 'وضعیت', 'ترتیب']" empty-message="منبعی تعریف نشده." :colspan="4">
        @foreach($sources as $source)
            <tr wire:key="source-{{ $source->id }}">
                <td dir="ltr">{{ $source->code }}</td>
                <td>{{ $source->title }}</td>
                <td><x-erp.ui.status-badge :label="$source->is_active ? 'فعال' : 'غیرفعال'" :tone="$source->is_active ? 'success' : 'warning'" /></td>
                <td>{{ $source->sort_order }}</td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    @foreach($pipelines as $pipeline)
        <h3 class="font-semibold mt-8 mb-3">پایپ‌لاین: {{ $pipeline->name }}</h3>
        <x-erp.ui.data-table :headers="['مرحله', 'احتمال', 'برنده', 'باخته', 'ترتیب']" empty-message="مرحله‌ای تعریف نشده." :colspan="5">
            @foreach($pipeline->stages as $stage)
                <tr wire:key="stage-{{ $stage->id }}">
                    <td>{{ $stage->name }}</td>
                    <td>{{ number_format((float) $stage->probability, 0) }}%</td>
                    <td>{{ $stage->is_won ? 'بله' : 'خیر' }}</td>
                    <td>{{ $stage->is_lost ? 'بله' : 'خیر' }}</td>
                    <td>{{ $stage->sort_order }}</td>
                </tr>
            @endforeach
        </x-erp.ui.data-table>
    @endforeach
</x-erp.ui.detail-page>
