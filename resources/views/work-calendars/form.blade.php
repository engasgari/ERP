<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $calendar->exists ? 'ویرایش تقویم کاری' : 'ثبت تقویم کاری' }}</h2></x-slot>
    <form method="POST" action="{{ $calendar->exists ? route('work-calendars.update', $calendar) : route('work-calendars.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($calendar->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-3">
            <label>کد تقویم<input name="code" value="{{ old('code', $calendar->code) }}" required class="w-full"></label>
            <label>نام تقویم<input name="name" value="{{ old('name', $calendar->name) }}" required class="w-full"></label>
            <label>سال شمسی<input type="number" name="jalali_year" value="{{ old('jalali_year', $calendar->jalali_year) }}" class="w-full"></label>
            <label>روزهای کاری، با ویرگول<textarea name="working_days" rows="2" class="w-full">{{ old('working_days', implode(',', $calendar->working_days ?: [])) }}</textarea></label>
            <label>تعطیلات هفتگی، با ویرگول<textarea name="weekend_days" rows="2" class="w-full">{{ old('weekend_days', implode(',', $calendar->weekend_days ?: [])) }}</textarea></label>
            <label>تعطیلات رسمی، با ویرگول<textarea name="holidays" rows="2" class="w-full">{{ old('holidays', implode(',', $calendar->holidays ?: [])) }}</textarea></label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $calendar->is_default))> پیش‌فرض</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $calendar->is_active ?? true))> فعال</label>
        </div>
        <label class="block">توضیحات<textarea name="description" rows="2" class="w-full">{{ old('description', $calendar->description) }}</textarea></label>
        <div class="flex justify-end gap-2"><a href="{{ route('work-calendars.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a><button class="erp-action-btn erp-action-edit">ذخیره</button></div>
    </form>
</x-app-layout>
