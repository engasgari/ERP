<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
            <h2 class="text-2xl font-bold mb-0">لیست انبارها</h2>
            <a href="{{ route('warehouses.create') }}" class="erp-action-btn erp-action-edit text-center">انبار جدید</a>
        </div>

        @include('livewire.partials.flash')

        <form wire:submit.prevent class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-5">نام انبار
                    <input wire:model.live.debounce.400ms="name" placeholder="نام یا توضیح">
                </label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-4">وضعیت
                    <select wire:model.live="status">
                        <option value="">همه</option>
                        <option value="active">فعال</option>
                        <option value="inactive">غیرفعال</option>
                    </select>
                </label>
                <div class="col-12 col-lg-3 d-grid">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </form>

        <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

        <div class="table-responsive overflow-x-auto">
            <table class="erp-ui-data-table w-full">
                <thead>
                    <tr>
                        <th>نام انبار</th>
                        <th>تاریخ ایجاد</th>
                        <th>وضعیت</th>
                        <th>شرح</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($warehouses as $warehouse)
                    <tr wire:key="warehouse-{{ $warehouse->id }}">
                        <td>
                            <input class="form-control form-control-sm" value="{{ $warehouse->name }}" wire:change="updateField({{ $warehouse->id }}, 'name', $event.target.value)">
                        </td>
                        <td class="text-nowrap">{{ formatJalaliDateSafe($warehouse->created_at) }}</td>
                        <td>
                            <select class="form-select form-select-sm" wire:change="updateField({{ $warehouse->id }}, 'is_active', $event.target.value)">
                                <option value="1" @selected($warehouse->is_active)>فعال</option>
                                <option value="0" @selected(! $warehouse->is_active)>غیرفعال</option>
                            </select>
                        </td>
                        <td>
                            <input class="form-control form-control-sm" value="{{ $warehouse->description }}" wire:change="updateField({{ $warehouse->id }}, 'description', $event.target.value)" placeholder="شرح">
                        </td>
                        <td>
                            <div class="d-grid d-sm-flex gap-2">
                                <button type="button" wire:click="show({{ $warehouse->id }})" class="erp-action-btn erp-action-detail">جزئیات</button>
                                <a href="{{ route('warehouses.show', $warehouse) }}" class="erp-action-btn erp-action-detail text-center">گزارش</a>
                                <button type="button" wire:click="delete({{ $warehouse->id }})" wire:confirm="آیا از حذف این انبار مطمئن هستید؟" wire:loading.attr="disabled" wire:target="delete({{ $warehouse->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-500 py-6">هنوز انباری ایجاد نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $warehouses->links() }}</div>
    </div>

    @if($showingWarehouse)
        <div class="erp-ui-modal-backdrop">
            <div class="erp-ui-modal-panel">
                <div class="erp-modal-header">
                    <h3>{{ $showingWarehouse->name }}</h3>
                    <button type="button" class="erp-modal-close" wire:click="closeModal">×</button>
                </div>
                <div class="erp-modal-body">
                    <div class="erp-modal-grid">
                        <div class="erp-modal-field">نام<div class="erp-modal-value">{{ $showingWarehouse->name }}</div></div>
                        <div class="erp-modal-field">وضعیت<div class="erp-modal-value">{{ $showingWarehouse->is_active ? 'فعال' : 'غیرفعال' }}</div></div>
                        <div class="erp-modal-field">تاریخ ایجاد<div class="erp-modal-value">{{ formatJalaliDateSafe($showingWarehouse->created_at) }}</div></div>
                        <div class="erp-modal-field">تعداد موجودی<div class="erp-modal-value">{{ $showingWarehouse->inventory_count }}</div></div>
                        <div class="erp-modal-field md:col-span-2">شرح<div class="erp-modal-value">{{ $showingWarehouse->description ?: '-' }}</div></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
