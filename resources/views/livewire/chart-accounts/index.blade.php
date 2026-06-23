<x-erp.ui.panel>
    <x-erp.ui.page-header
        title="سرفصل‌های مالی"
        :actions="[
            ['label' => 'سرفصل جدید', 'url' => route('chart-accounts.create'), 'class' => 'erp-action-edit'],
        ]"
    />

    @include('livewire.partials.flash')

    <x-erp.ui.filter-bar wire:submit.prevent>
        <div class="row g-2 g-md-3 align-items-end">
            <label class="erp-filter-field col-12 col-md-6 col-lg-4">جستجو
                <input wire:model.live.debounce.400ms="search" placeholder="کد، عنوان یا والد">
            </label>

            <label class="erp-filter-field col-12 col-md-6 col-lg-2">سطح
                <select wire:model.live="level">
                    <option value="">همه</option>
                    @foreach($levelLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-filter-field col-12 col-md-6 col-lg-2">ماهیت
                <select wire:model.live="nature">
                    <option value="">همه</option>
                    @foreach($natureLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-filter-field col-12 col-md-6 col-lg-2">نوع
                <select wire:model.live="is_system">
                    <option value="">همه</option>
                    <option value="1">سیستمی</option>
                    <option value="0">دستی</option>
                </select>
            </label>

            <div class="col-12 col-lg-2 d-grid">
                <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
            </div>
        </div>
    </x-erp.ui.filter-bar>

    <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

    <div wire:loading.remove>
        <x-erp.ui.data-table :headers="['کد', 'عنوان', 'سطح', 'ماهیت', 'والد', 'نوع', 'عملیات']" colspan="7" empty-message="سرفصلی یافت نشد.">
            @forelse($accounts as $account)
                <tr wire:key="account-{{ $account->id }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('financial-reports.account-statement', $account) }}'">
                    <td>{{ $account->code }}</td>
                    <td>
                        <span class="font-bold text-blue-700">{{ chartAccountDisplayLabel($account) }}</span>
                    </td>
                    <td>{{ $levelLabels[$account->level] ?? $account->level }}</td>
                    <td>{{ $natureLabels[$account->nature] ?? $account->nature }}</td>
                    <td>{{ $account->parent_title ?: '-' }}</td>
                    <td>
                        <x-erp.ui.status-badge :label="$account->is_system ? 'سیستمی' : 'دستی'" :tone="$account->is_system ? 'info' : 'neutral'" />
                    </td>
                    <td onclick="event.stopPropagation()">
                        <x-erp.ui.action-menu :actions="[
                            ['type' => 'button', 'label' => 'جزئیات', 'class' => 'erp-action-detail', 'attrs' => ['wire:click' => 'show(' . $account->id . ')']],
                            ['label' => 'ویرایش', 'url' => route('chart-accounts.edit', $account), 'class' => 'erp-action-edit'],
                            ['type' => 'button', 'label' => 'حذف', 'class' => 'erp-action-delete', 'attrs' => ['wire:click' => 'delete(' . $account->id . ')', 'wire:confirm' => 'آیا از حذف این سرفصل مطمئن هستید؟', 'wire:loading.attr' => 'disabled', 'wire:target' => 'delete(' . $account->id . ')']],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-500">سرفصلی یافت نشد.</td></tr>
            @endforelse
        </x-erp.ui.data-table>
    </div>

    <div wire:loading.remove>{{ $accounts->links() }}</div>

    @if($showingAccount)
        <x-erp.ui.details-modal
            :title="$showingAccount->title"
            :actions="[
                ['label' => 'ویرایش', 'url' => route('chart-accounts.edit', $showingAccount), 'class' => 'erp-action-edit'],
            ]"
        >
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
            </x-slot>
            <div class="erp-modal-grid">
                <div class="erp-modal-field">کد<div class="erp-modal-value">{{ $showingAccount->code }}</div></div>
                <div class="erp-modal-field">عنوان<div class="erp-modal-value">{{ chartAccountDisplayLabel($showingAccount) }}</div></div>
                <div class="erp-modal-field">سطح<div class="erp-modal-value">{{ $levelLabels[$showingAccount->level] ?? $showingAccount->level }}</div></div>
                <div class="erp-modal-field">ماهیت<div class="erp-modal-value">{{ $natureLabels[$showingAccount->nature] ?? $showingAccount->nature }}</div></div>
                <div class="erp-modal-field">والد<div class="erp-modal-value">{{ $showingAccount->parent_title ?: '-' }}</div></div>
                <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $showingAccount->is_system ? 'سیستمی' : 'دستی' }}</div></div>
            </div>
        </x-erp.ui.details-modal>
    @endif
</x-erp.ui.panel>
