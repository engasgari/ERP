<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $shift->exists ? 'ویرایش شیفت کاری' : 'ثبت شیفت کاری' }}</h2></x-slot>

    <form method="POST" action="{{ $shift->exists ? route('work-shifts.update', $shift) : route('work-shifts.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($shift->exists) @method('PUT') @endif

        <div class="grid gap-4 md:grid-cols-4">
            <label>کد شیفت<input name="code" value="{{ old('code', $shift->code) }}" required class="w-full"></label>
            <label>نام شیفت<input name="name" value="{{ old('name', $shift->name) }}" required class="w-full"></label>
            <label>ساعت شروع<input type="time" name="start_time" value="{{ old('start_time', $shift->start_time) }}" required class="w-full"></label>
            <label>ساعت پایان<input type="time" name="end_time" value="{{ old('end_time', $shift->end_time) }}" required class="w-full"></label>
            <label>استراحت دقیقه<input type="number" name="break_minutes" value="{{ old('break_minutes', $shift->break_minutes ?? 0) }}" class="w-full"></label>
            <label>ساعت کار روزانه<input type="number" step="0.01" name="daily_work_hours" value="{{ old('daily_work_hours', $shift->daily_work_hours ?? 8) }}" class="w-full"></label>
            <label>ضریب اضافه‌کاری<input type="number" step="0.01" name="overtime_multiplier" value="{{ old('overtime_multiplier', $shift->overtime_multiplier ?? 1.4) }}" class="w-full"></label>
            <label>تلرانس تاخیر دقیقه<input type="number" name="late_tolerance_minutes" value="{{ old('late_tolerance_minutes', $shift->late_tolerance_minutes ?? 0) }}" class="w-full"></label>
            <label>تلرانس تعجیل دقیقه<input type="number" name="early_leave_tolerance_minutes" value="{{ old('early_leave_tolerance_minutes', $shift->early_leave_tolerance_minutes ?? 0) }}" class="w-full"></label>
            <label class="flex items-center gap-2 mt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $shift->is_active ?? true))> فعال</label>
        </div>

        <label class="block">توضیحات<textarea name="description" rows="2" class="w-full">{{ old('description', $shift->description) }}</textarea></label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('work-shifts.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            <button class="erp-action-btn erp-action-edit">ذخیره</button>
        </div>
    </form>
</x-app-layout>
