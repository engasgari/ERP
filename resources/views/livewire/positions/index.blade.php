<div class="py-4 py-md-5"><div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <h2 class="text-xl font-bold mb-0">پست‌های سازمانی</h2>
    @include('livewire.partials.flash')
    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form wire:submit.prevent="save" class="erp-ui-filter-bar"><div class="row g-2 g-md-3 align-items-end">
        <label class="erp-filter-field col-12 col-md-3">کد<input wire:model="form.code"></label><label class="erp-filter-field col-12 col-md-3">عنوان<input wire:model="form.title"></label>
        <label class="erp-filter-field col-12 col-md-3">شغل<select wire:model="form.job_id"><option value="">-</option>@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->title }}</option>@endforeach</select></label>
        <label class="erp-filter-field col-12 col-md-3">واحد<select wire:model="form.organization_unit_id"><option value="">-</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->title }}</option>@endforeach</select></label>
        <label class="erp-filter-field col-12 col-md-3">سرپرست<select wire:model="form.supervisor_position_id"><option value="">-</option>@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->title }}</option>@endforeach</select></label>
        <label class="erp-filter-field col-12 col-md-2">ظرفیت<input type="number" wire:model="form.capacity"></label><label class="erp-filter-field col-12 col-md-2">وضعیت<select wire:model="form.is_active"><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
        <label class="erp-filter-field col-12 col-md-3">شرح<input wire:model="form.description"></label><div class="col-12 col-md-2 d-grid d-sm-flex gap-2"><button class="erp-action-btn erp-action-edit">ذخیره</button><button type="button" wire:click="cancel" class="erp-action-btn">جدید</button></div>
    </div></form>
    <div class="erp-ui-filter-bar"><div class="row g-2"><label class="erp-filter-field col-12 col-md-9">جستجو<input wire:model.live.debounce.400ms="search"></label><div class="col-12 col-md-3 d-grid"><button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلتر</button></div></div></div>
    <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>کد</th><th>عنوان</th><th>شغل</th><th>واحد</th><th>سرپرست</th><th>ظرفیت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>@forelse($positions as $position)<tr wire:key="position-{{ $position->id }}"><td>{{ $position->code }}</td><td>{{ $position->title }}</td><td>{{ $position->job?->title ?: '-' }}</td><td>{{ $position->organizationUnit?->title ?: '-' }}</td><td>{{ $position->supervisor?->title ?: '-' }}</td><td>{{ $position->capacity }}</td><td>{{ $position->is_active ? 'فعال' : 'غیرفعال' }}</td><td><div class="d-grid d-sm-flex gap-2"><button wire:click="edit({{ $position->id }})" class="erp-action-btn erp-action-edit">ویرایش</button><button wire:click="delete({{ $position->id }})" wire:confirm="حذف شود؟" class="erp-action-btn erp-action-delete">حذف</button></div></td></tr>@empty<tr><td colspan="8" class="text-center py-6 text-slate-500">رکوردی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    {{ $positions->links() }}
</div></div>
