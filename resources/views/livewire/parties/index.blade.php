<div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
        <h2 class="text-lg font-bold mb-0">فهرست اشخاص و شرکت‌ها</h2>
        <a href="{{ route('parties.create') }}" class="erp-action-btn erp-action-edit text-center">تعریف شخص/شرکت</a>
    </div>

    @include('livewire.partials.flash')

    <form wire:submit.prevent class="erp-ui-filter-bar">
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
    </form>

    <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

    <div class="table-responsive overflow-x-auto">
        <table class="erp-ui-data-table min-w-full">
            <thead>
                <tr>
                    <th>کد</th>
                    <th>کد تفصیل</th>
                    <th>نام</th>
                    <th>نوع</th>
                    <th>گروه‌ها</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            @forelse($parties as $party)
                <tr wire:key="party-{{ $party->id }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('financial-reports.statement', ['party_id' => $party->id]) }}'">
                    <td class="text-nowrap">{{ $party->code }}</td>
                    <td class="text-nowrap">{{ $party->detail_code ?: '-' }}</td>
                    <td>
                        <span class="font-bold text-blue-700">{{ $party->name }}</span>
                    </td>
                    <td>{{ $party->kind === 'company' ? 'شرکت' : 'شخص' }}</td>
                    <td>{{ $party->types->pluck('title')->join('، ') ?: '-' }}</td>
                    <td onclick="event.stopPropagation()">
                        <div class="d-grid d-sm-flex gap-2">
                            <button type="button" wire:click="show({{ $party->id }})" class="erp-action-btn erp-action-detail">جزئیات</button>
                            <a href="{{ route('parties.edit', $party) }}" class="erp-action-btn erp-action-edit text-center">ویرایش کامل</a>
                            <button type="button" wire:click="delete({{ $party->id }})" wire:confirm="آیا از حذف این شخص/شرکت مطمئن هستید؟" wire:loading.attr="disabled" wire:target="delete({{ $party->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-gray-500 py-6">موردی یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $parties->links() }}</div>

    @if($showingParty)
        <div class="erp-ui-modal-backdrop">
            <div class="erp-ui-modal-panel">
                <div class="erp-modal-header">
                    <h3>{{ $showingParty->name }}</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
                </div>
                <div class="erp-modal-body">
                    <div class="erp-modal-grid">
                        <div class="erp-modal-field">کد<div class="erp-modal-value">{{ $showingParty->code }}</div></div>
                        <div class="erp-modal-field">کد تفصیل<div class="erp-modal-value">{{ $showingParty->detail_code }}</div></div>
                        <div class="erp-modal-field">نوع<div class="erp-modal-value">{{ $showingParty->kind === 'company' ? 'شرکت' : 'شخص' }}</div></div>
                        <div class="erp-modal-field">گروه‌ها<div class="erp-modal-value">{{ $showingParty->types->pluck('title')->join('، ') ?: '-' }}</div></div>
                        <div class="erp-modal-field">شناسه/کد ملی<div class="erp-modal-value">{{ $showingParty->national_id ?: '-' }}</div></div>
                        <div class="erp-modal-field">کد اقتصادی<div class="erp-modal-value">{{ $showingParty->economic_code ?: '-' }}</div></div>
                        <div class="erp-modal-field">موبایل<div class="erp-modal-value">{{ $showingParty->mobile ?: '-' }}</div></div>
                        <div class="erp-modal-field">تلفن<div class="erp-modal-value">{{ $showingParty->phone ?: '-' }}</div></div>
                        <div class="erp-modal-field">ایمیل<div class="erp-modal-value">{{ $showingParty->email ?: '-' }}</div></div>
                        <div class="erp-modal-field">کد پستی<div class="erp-modal-value">{{ $showingParty->postal_code ?: '-' }}</div></div>
                        <div class="erp-modal-field">مانده حساب<div class="erp-modal-value">{{ number_format($showingParty->ledger_balance) }}</div></div>
                        <div class="erp-modal-field md:col-span-2">آدرس<div class="erp-modal-value">{{ $showingParty->address ?: '-' }}</div></div>
                        <div class="erp-modal-field md:col-span-2">یادداشت<div class="erp-modal-value">{{ $showingParty->notes ?: '-' }}</div></div>
                    </div>
                </div>
                <div class="erp-modal-actions">
                    <a href="{{ route('parties.edit', $showingParty) }}" class="erp-action-btn erp-action-edit">ویرایش کامل</a>
                </div>
            </div>
        </div>
    @endif
</div>
