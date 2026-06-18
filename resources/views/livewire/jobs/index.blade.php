<div class="py-4 py-md-5"><div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <h2 class="text-xl font-bold mb-0">مشاغل</h2>
    @include('livewire.partials.flash')
    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form wire:submit.prevent="save" class="erp-ui-filter-bar"><div class="row g-2 g-md-3 align-items-end">
        <label class="erp-filter-field col-12 col-md-3">کد<input wire:model="form.code"></label>
        <label class="erp-filter-field col-12 col-md-4">عنوان<input wire:model="form.title"></label>
        <label class="erp-filter-field col-12 col-md-2">وضعیت<select wire:model="form.is_active"><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
        <label class="erp-filter-field col-12 col-md-6">شرح<input wire:model="form.description"></label>
        <div class="col-12 col-md-3 d-grid d-sm-flex gap-2"><button class="erp-action-btn erp-action-edit">ذخیره</button><button type="button" wire:click="cancel" class="erp-action-btn">جدید</button></div>
    </div></form>
    <div class="erp-ui-filter-bar"><div class="row g-2"><label class="erp-filter-field col-12 col-md-9">جستجو<input wire:model.live.debounce.400ms="search"></label><div class="col-12 col-md-3 d-grid"><button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلتر</button></div></div></div>
    <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>کد</th><th>عنوان</th><th>شرح</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>@forelse($jobs as $job)<tr wire:key="job-{{ $job->id }}"><td>{{ $job->code }}</td><td>{{ $job->title }}</td><td>{{ $job->description ?: '-' }}</td><td>{{ $job->is_active ? 'فعال' : 'غیرفعال' }}</td><td><div class="d-grid d-sm-flex gap-2"><button wire:click="edit({{ $job->id }})" class="erp-action-btn erp-action-edit">ویرایش</button><button wire:click="delete({{ $job->id }})" wire:confirm="حذف شود؟" class="erp-action-btn erp-action-delete">حذف</button></div></td></tr>@empty<tr><td colspan="5" class="text-center py-6 text-slate-500">رکوردی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    {{ $jobs->links() }}
</div></div>
