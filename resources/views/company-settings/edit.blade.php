<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">تنظیمات اطلاعات شرکت</h2>
    </x-slot>

    <form method="POST" action="{{ route('company-settings.update') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @method('PUT')

        <div class="grid gap-4 md:grid-cols-2">
            <label>نام شرکت
                <input name="company_name" value="{{ old('company_name', $company->company_name) }}" class="w-full">
            </label>

            <label>شماره ثبت
                <input name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" class="w-full">
            </label>

            <label>کد اقتصادی
                <input name="economic_code" value="{{ old('economic_code', $company->economic_code) }}" class="w-full">
            </label>

            <label>شناسه ملی / کد ملی
                <input name="national_id" value="{{ old('national_id', $company->national_id) }}" class="w-full">
            </label>

            <label>کد پستی
                <input name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" class="w-full">
            </label>

            <label>تلفن
                <input name="phone" value="{{ old('phone', $company->phone) }}" class="w-full">
            </label>

            <label>درصد پیش‌فرض ارزش افزوده
                <input name="default_vat_rate" type="number" min="0" step="0.01" value="{{ old('default_vat_rate', $company->default_vat_rate ?? 0) }}" class="w-full">
            </label>
        </div>

        <label class="block">آدرس
            <textarea name="address" rows="3" class="w-full">{{ old('address', $company->address) }}</textarea>
        </label>

        @if($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end">
            <button class="bg-blue-500 text-white px-4 py-2 rounded">ذخیره اطلاعات شرکت</button>
        </div>
    </form>
</x-app-layout>
