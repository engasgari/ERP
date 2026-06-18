<div class="py-4 py-md-5"><div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <h2 class="text-xl font-bold mb-0">مدارک پرسنلی</h2>
    @include('livewire.partials.flash')
    <form wire:submit.prevent="save" class="erp-ui-filter-bar"><div class="row g-2 g-md-3 align-items-end">
        <label class="erp-filter-field col-12 col-md-3">پرسنل<select wire:model="form.employee_id"><option value="">انتخاب کنید</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->full_name }}</option>@endforeach</select></label>
        <label class="erp-filter-field col-12 col-md-2">نوع مدرک<select wire:model="form.document_type"><option value="national">هویتی</option><option value="insurance">بیمه</option><option value="employment">استخدامی</option><option value="attachment">پیوست</option></select></label>
        <label class="erp-filter-field col-12 col-md-3">عنوان<input wire:model="form.title"></label>
        <label class="erp-filter-field col-12 col-md-4">مسیر فایل<input wire:model="form.file_path" dir="ltr"></label>
        <label class="erp-filter-field col-12 col-md-2">تاریخ صدور<input wire:model="form.issued_at" dir="ltr"></label>
        <label class="erp-filter-field col-12 col-md-2">تاریخ انقضا<input wire:model="form.expires_at" dir="ltr"></label>
        <label class="erp-filter-field col-12 col-md-5">یادداشت<input wire:model="form.notes"></label>
        <div class="col-12 col-md-3 d-grid d-sm-flex gap-2"><button class="erp-action-btn erp-action-edit">ذخیره</button><button type="button" wire:click="cancel" class="erp-action-btn">جدید</button></div>
    </div></form>
    <div class="erp-ui-filter-bar"><div class="row g-2"><label class="erp-filter-field col-12 col-md-9">جستجو<input wire:model.live.debounce.400ms="search"></label><div class="col-12 col-md-3 d-grid"><button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلتر</button></div></div></div>
    <div class="table-responsive"><table class="erp-ui-data-table w-full"><thead><tr><th>پرسنل</th><th>نوع</th><th>عنوان</th><th>صدور</th><th>انقضا</th><th>فایل</th><th>عملیات</th></tr></thead><tbody>@forelse($documents as $document)<tr wire:key="document-{{ $document->id }}"><td>{{ $document->employee->full_name }}</td><td>{{ $document->document_type }}</td><td>{{ $document->title }}</td><td>{{ formatJalaliDateSafe($document->issued_at, '-') }}</td><td>{{ formatJalaliDateSafe($document->expires_at, '-') }}</td><td dir="ltr">{{ $document->file_path ?: '-' }}</td><td><div class="d-grid d-sm-flex gap-2"><button wire:click="edit({{ $document->id }})" class="erp-action-btn erp-action-edit">ویرایش</button><button wire:click="delete({{ $document->id }})" wire:confirm="حذف شود؟" class="erp-action-btn erp-action-delete">حذف</button></div></td></tr>@empty<tr><td colspan="7" class="text-center py-6 text-slate-500">مدرکی ثبت نشده است.</td></tr>@endforelse</tbody></table></div>
    {{ $documents->links() }}
</div></div>
