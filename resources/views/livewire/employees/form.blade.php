<div class="py-4 py-md-5">
    <div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4 max-w-6xl mx-auto">
        @include('livewire.partials.flash')

        <form wire:submit.prevent="save" class="space-y-5">
            <section class="rounded-md border border-slate-200 p-3 p-md-4">
                <h3 class="font-bold text-slate-800 mb-3">اطلاعات شخص</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="erp-filter-field">انتخاب از اشخاص موجود
                        <select wire:model.live="form.party_id">
                            <option value="">ایجاد شخص جدید</option>
                            @foreach($parties as $party)
                                <option value="{{ $party->id }}">{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">نام و نام خانوادگی *
                        <input wire:model="form.party_name" placeholder="مثال: علی رضایی">
                        @error('form.party_name') <span class="erp-filter-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="erp-filter-field">کد ملی
                        <input wire:model="form.national_id" dir="ltr">
                    </label>
                    <label class="erp-filter-field">موبایل
                        <input wire:model="form.mobile" dir="ltr">
                    </label>
                    <label class="erp-filter-field">تلفن
                        <input wire:model="form.phone" dir="ltr">
                    </label>
                    <label class="erp-filter-field">ایمیل
                        <input wire:model="form.email" dir="ltr">
                        @error('form.email') <span class="erp-filter-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="erp-filter-field md:col-span-2 lg:col-span-3">آدرس
                        <textarea wire:model="form.address" rows="2"></textarea>
                    </label>
                </div>
            </section>

            <section class="rounded-md border border-slate-200 p-3 p-md-4">
                <h3 class="font-bold text-slate-800 mb-3">اطلاعات استخدامی</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="erp-filter-field">کد پرسنلی *
                        <input wire:model="form.personnel_code" dir="ltr">
                        @error('form.personnel_code') <span class="erp-filter-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="erp-filter-field">شماره کارت تردد
                        <input wire:model="form.attendance_card_number" dir="ltr">
                        @error('form.attendance_card_number') <span class="erp-filter-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="erp-filter-field">نوع استخدام
                        <select wire:model="form.employment_type">
                            <option value="hourly">ساعتی</option>
                            <option value="monthly_contract">قرارداد ماهانه</option>
                            <option value="project_based">پروژه‌ای</option>
                            <option value="fixed_term">مدت معین</option>
                            <option value="permanent">دائم</option>
                        </select>
                    </label>
                    <label class="erp-filter-field">تاریخ استخدام *
                        <input wire:model="form.hire_date" dir="ltr" placeholder="1403/01/01">
                    </label>
                    <label class="erp-filter-field">تاریخ پایان همکاری
                        <input wire:model="form.termination_date" dir="ltr" placeholder="1403/12/29">
                    </label>
                    <label class="erp-filter-field">شماره بیمه
                        <input wire:model="form.insurance_number" dir="ltr">
                    </label>
                    <label class="erp-filter-field">واحد سازمانی
                        <select wire:model="form.organization_unit_id">
                            <option value="">تعیین نشده</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">پست سازمانی
                        <select wire:model="form.position_id">
                            <option value="">تعیین نشده</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}">{{ $position->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">پروژه پیش‌فرض
                        <select wire:model="form.default_project_id">
                            <option value="">بدون پروژه</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select wire:model="form.status">
                            <option value="active">فعال</option>
                            <option value="inactive">غیرفعال</option>
                            <option value="terminated">پایان همکاری</option>
                            <option value="archived">بایگانی</option>
                        </select>
                    </label>
                    <label class="erp-filter-field md:col-span-2 lg:col-span-3">یادداشت
                        <textarea wire:model="form.notes" rows="2"></textarea>
                    </label>
                </div>
            </section>

            <div class="d-grid d-sm-flex gap-2">
                <button type="submit" wire:loading.attr="disabled" class="erp-action-btn erp-action-edit">ذخیره پرونده</button>
                <a href="{{ route('employees.index') }}" class="erp-action-btn text-center">بازگشت</a>
            </div>
        </form>
    </div>
</div>
