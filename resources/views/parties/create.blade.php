@php($isEdit = isset($party) && $party->exists)

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش شخص/شرکت' : 'تعریف شخص/شرکت' }}</h2>
    </x-slot>

    <form method="POST" action="{{ $isEdit ? route('parties.update', $party) : route('parties.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                <div class="font-semibold mb-2">لطفاً خطاهای فرم را بررسی کنید.</div>
                <ul class="list-disc pr-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <label class="grid gap-1">نوع
                <select name="kind" class="w-full rounded-md border-gray-300">
                    <option value="person" @selected(old('kind', $party->kind ?? 'person') === 'person')>شخص</option>
                    <option value="company" @selected(old('kind', $party->kind ?? '') === 'company')>شرکت</option>
                </select>
            </label>
            <label class="grid gap-1 md:col-span-2">نام
                <input name="name" required class="w-full rounded-md border-gray-300" value="{{ old('name', $party->name ?? '') }}">
            </label>
            <label class="grid gap-1">شناسه/کد ملی
                <input name="national_id" class="w-full rounded-md border-gray-300" value="{{ old('national_id', $party->national_id ?? '') }}">
            </label>
            <label class="grid gap-1">کد اقتصادی
                <input name="economic_code" class="w-full rounded-md border-gray-300" value="{{ old('economic_code', $party->economic_code ?? '') }}">
            </label>
            <label class="grid gap-1">کد پستی
                <input name="postal_code" class="w-full rounded-md border-gray-300" value="{{ old('postal_code', $party->postal_code ?? '') }}">
            </label>
            <label class="grid gap-1">موبایل
                <input name="mobile" class="w-full rounded-md border-gray-300" value="{{ old('mobile', $party->mobile ?? '') }}">
            </label>
            <label class="grid gap-1">تلفن
                <input name="phone" class="w-full rounded-md border-gray-300" value="{{ old('phone', $party->phone ?? '') }}">
            </label>
            <label class="grid gap-1">ایمیل
                <input name="email" type="email" class="w-full rounded-md border-gray-300" value="{{ old('email', $party->email ?? '') }}">
            </label>
        </div>

        <div>
            <div class="text-sm font-semibold text-gray-700 mb-2">گروه‌ها</div>
            <div class="grid gap-2 md:grid-cols-4">
                @foreach($types as $type)
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                        <input type="checkbox" name="types[]" value="{{ $type->id }}" @checked(collect(old('types', isset($party) ? $party->types->pluck('id')->all() : []))->contains($type->id))>
                        <span>{{ $type->title }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label class="grid gap-1">آدرس
            <textarea name="address" class="w-full rounded-md border-gray-300" rows="3">{{ old('address', $party->address ?? '') }}</textarea>
        </label>

        <label class="grid gap-1">یادداشت
            <textarea name="notes" class="w-full rounded-md border-gray-300" rows="3">{{ old('notes', $party->notes ?? '') }}</textarea>
        </label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('parties.index') }}" class="erp-action-btn">بازگشت</a>
            <button class="erp-action-btn erp-action-edit">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</button>
        </div>
    </form>
</x-app-layout>
