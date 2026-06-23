@php($isEdit = $project->exists)

@if($errors->any())
    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm font-semibold text-red-700">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('projects.update', $project) : route('projects.store') }}" class="space-y-4">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <label class="grid gap-1 text-sm font-bold text-slate-600">
            شماره پروژه
            <input name="project_number" value="{{ old('project_number', $project->project_number) }}" placeholder="خالی = شماره خودکار" class="rounded-md border-slate-300">
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
            نام پروژه *
            <input name="name" required value="{{ old('name', $project->name) }}" class="rounded-md border-slate-300">
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            کارفرما / مشتری
            <select name="party_id" class="rounded-md border-slate-300">
                <option value="">انتخاب نشده</option>
                @foreach($parties as $party)
                    <option value="{{ $party->id }}" @selected(old('party_id', $project->party_id) == $party->id)>{{ $party->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            مدیر پروژه
            <select name="project_manager_id" class="rounded-md border-slate-300">
                <option value="">انتخاب نشده</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected(old('project_manager_id', $project->project_manager_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            وضعیت
            <select name="status" class="rounded-md border-slate-300">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $project->status ?: 'planning') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            تاریخ شروع
            <input name="start_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('start_date'), $project->start_date) }}" class="rounded-md border-slate-300">
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            تاریخ پایان
            <input name="end_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('end_date'), $project->end_date) }}" class="rounded-md border-slate-300">
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            بودجه پروژه
            <input name="budget" type="number" step="0.01" min="0" dir="ltr" value="{{ old('budget', $project->budget) }}" class="rounded-md border-slate-300">
        </label>

        <label class="grid gap-1 text-sm font-bold text-slate-600">
            کد مرکز هزینه
            <input name="cost_center_code" value="{{ old('cost_center_code', $project->cost_center_code) }}" class="rounded-md border-slate-300">
        </label>
    </div>

    <label class="grid gap-1 text-sm font-bold text-slate-600">
        توضیحات
        <textarea name="description" rows="4" class="rounded-md border-slate-300">{{ old('description', $project->description) }}</textarea>
    </label>

    <div class="flex justify-end gap-2">
        <a href="{{ route('projects.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">انصراف</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white">{{ $isEdit ? 'به‌روزرسانی پروژه' : 'ثبت پروژه' }}</button>
    </div>
</form>

