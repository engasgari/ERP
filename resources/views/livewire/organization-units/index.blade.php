<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <h2 class="text-xl font-bold mb-0">واحدهای سازمانی</h2>
        </div>
        @include('livewire.partials.flash')
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <form wire:submit.prevent="save" class="erp-ui-filter-bar">
            <div class="row g-2 g-md-3 align-items-end">
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">کد<input wire:model="form.code"></label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">عنوان<input wire:model="form.title"></label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">نوع<select wire:model="form.type"><option value="company">شرکت</option><option value="department">دپارتمان</option><option value="division">بخش</option><option value="team">تیم</option></select></label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-3">والد<select wire:model="form.parent_id"><option value="">بدون والد</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->title }}</option>@endforeach</select></label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">مرکز هزینه<input wire:model="form.cost_center_code"></label>
                <label class="erp-filter-field col-12 col-md-6 col-lg-2">وضعیت<select wire:model="form.is_active"><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
                <label class="erp-filter-field col-12 col-lg-7">شرح<input wire:model="form.description"></label>
                <div class="col-12 col-lg-3 d-grid d-sm-flex gap-2"><button class="erp-action-btn erp-action-edit">ذخیره</button><button type="button" wire:click="cancel" class="erp-action-btn">جدید</button></div>
            </div>
        </form>
        <div class="erp-ui-filter-bar"><div class="row g-2"><label class="erp-filter-field col-12 col-md-9">جستجو<input wire:model.live.debounce.400ms="search"></label><div class="col-12 col-md-3 d-grid"><button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلتر</button></div></div></div>
        <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>
        <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>کد</th><th>عنوان</th><th>نوع</th><th>والد</th><th>مرکز هزینه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>@forelse($units as $unit)<tr wire:key="unit-{{ $unit->id }}"><td>{{ $unit->code }}</td><td>{{ $unit->title }}</td><td>{{ $unit->type }}</td><td>{{ $unit->parent?->title ?: '-' }}</td><td>{{ $unit->cost_center_code ?: '-' }}</td><td>{{ $unit->is_active ? 'فعال' : 'غیرفعال' }}</td><td><div class="d-grid d-sm-flex gap-2"><button wire:click="edit({{ $unit->id }})" class="erp-action-btn erp-action-edit">ویرایش</button><button wire:click="delete({{ $unit->id }})" wire:confirm="حذف شود؟" class="erp-action-btn erp-action-delete">حذف</button></div></td></tr>@empty<tr><td colspan="7" class="text-center py-6 text-slate-500">رکوردی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
        {{ $units->links() }}
    </div>
</div>
