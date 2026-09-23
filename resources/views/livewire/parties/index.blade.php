<x-erp.ui.list-page
    title="فهرست اشخاص و شرکت‌ها"
    description="مدیریت مشتریان، فروشندگان و طرف‌های حساب تجاری."
    route="parties.index"
    :actions="[
        ['label' => 'تعریف شخص/شرکت', 'url' => route('parties.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-lg-5">جستجو
                    <input wire:model.live.debounce.400ms="search" placeholder="نام، کد، کد تفصیل، کد پستی">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">نوع
                    <select wire:model.live="kind">
                        <option value="">همه</option>
                        <option value="person">شخص</option>
                        <option value="company">شرکت</option>
                    </select>
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">گروه
                    <select wire:model.live="type_id">
                        <option value="">همه</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="col-12 col-lg-2 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <x-erp.ui.data-table
        :headers="['کد', 'کد تفصیل', 'نام', 'نوع', 'گروه‌ها', 'عملیات']"
        empty-message="موردی یافت نشد."
        :colspan="6"
    >
        @foreach($parties as $party)
            <tr wire:key="party-{{ $party->id }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('financial-reports.statement', ['party_id' => $party->id]) }}'">
                <td class="text-nowrap">{{ $party->code }}</td>
                <td class="text-nowrap">{{ $party->detail_code ?: '-' }}</td>
                <td><span class="font-bold text-blue-700">{{ $party->name }}</span></td>
                <td>{{ $party->kind === 'company' ? 'شرکت' : 'شخص' }}</td>
                <td>{{ $party->types->pluck('title')->join('، ') ?: '-' }}</td>
                <td onclick="event.stopPropagation()">
                    <x-erp.ui.action-menu :actions="[
                        ['type' => 'button', 'label' => 'جزئیات', 'class' => 'erp-action-detail', 'attrs' => ['wire:click' => 'show(' . $party->id . ')']],
                        ['label' => 'ویرایش کامل', 'url' => route('parties.edit', $party), 'class' => 'erp-action-edit'],
                        ['type' => 'button', 'label' => 'حذف', 'class' => 'erp-action-delete', 'attrs' => ['wire:click' => 'delete(' . $party->id . ')', 'wire:confirm' => 'آیا از حذف این شخص/شرکت مطمئن هستید؟', 'wire:loading.attr' => 'disabled', 'wire:target' => 'delete(' . $party->id . ')']],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $parties->links() }}</div>

    @if($showingParty)
        <x-erp.ui.details-modal :title="$showingParty->name" :actions="[
            ['label' => 'ویرایش کامل', 'url' => route('parties.edit', $showingParty), 'class' => 'erp-action-edit'],
        ]">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <div class="erp-modal-grid">
                <div class="erp-modal-field">کد<div class="erp-modal-value">{{ $showingParty->code }}</div></div>
                <div class="erp-modal-field">کد تفصیل<div class="erp-modal-value">{{ $showingParty->detail_code ?: '-' }}</div></div>
                <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $showingParty->kind === 'company' ? 'شرکت' : 'شخص' }}</div></div>
                <div class="erp-modal-field">گروه‌ها<div class="erp-modal-value">{{ $showingParty->types->pluck('title')->join('، ') ?: '-' }}</div></div>
                <div class="erp-modal-field">موبایل<div class="erp-modal-value">{{ $showingParty->mobile ?: '-' }}</div></div>
                <div class="erp-modal-field">کد ملی/شناسه<div class="erp-modal-value">{{ $showingParty->national_id ?: '-' }}</div></div>
            </div>
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.list-page>
