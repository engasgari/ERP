<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $group->exists ? 'ویرایش گروه کاری' : 'ثبت گروه کاری' }}</h2></x-slot>
    <form method="POST" action="{{ $group->exists ? route('work-groups.update', $group) : route('work-groups.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($group->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-4">
            <label>کد گروه<input name="code" value="{{ old('code', $group->code) }}" required class="w-full"></label>
            <label>نام گروه<input name="name" value="{{ old('name', $group->name) }}" required class="w-full"></label>
            <label>شیفت پیش‌فرض<select name="work_shift_id" class="w-full"><option value="">بدون شیفت</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}" @selected(old('work_shift_id', $group->work_shift_id) == $shift->id)>{{ $shift->name }}</option>@endforeach</select></label>
            <label>تقویم پیش‌فرض<select name="work_calendar_id" class="w-full"><option value="">بدون تقویم</option>@foreach($calendars as $calendar)<option value="{{ $calendar->id }}" @selected(old('work_calendar_id', $group->work_calendar_id) == $calendar->id)>{{ $calendar->name }}</option>@endforeach</select></label>
            <label class="flex items-center gap-2 mt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active ?? true))> فعال</label>
        </div>
        <label class="block">توضیحات<textarea name="description" rows="2" class="w-full">{{ old('description', $group->description) }}</textarea></label>
        <div class="flex justify-end gap-2"><a href="{{ route('work-groups.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a><button class="erp-action-btn erp-action-edit">ذخیره</button></div>
    </form>
</x-app-layout>
