@php
    $isEdit = isset($party) && $party->exists;
    $selectedTypeIds = $selectedTypeIds ?? [];
    $shareholderTypeId = $shareholderTypeId ?? null;
    $selectedPartnerCode = $selectedPartnerCode ?? '';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش شخص/شرکت' : 'تعریف شخص/شرکت' }}</h2>
    </x-slot>

    <form
        method="POST"
        action="{{ $isEdit ? route('parties.update', $party) : route('parties.store') }}"
        class="bg-white rounded-lg shadow-md p-6 space-y-5"
        x-data="{
            selectedTypes: @js($selectedTypeIds),
            shareholderTypeId: @js($shareholderTypeId),
            toggleType(typeId) {
                typeId = Number(typeId);
                if (this.selectedTypes.includes(typeId)) {
                    this.selectedTypes = this.selectedTypes.filter(id => id !== typeId);
                } else {
                    this.selectedTypes.push(typeId);
                }
            },
            get isShareholder() {
                return this.shareholderTypeId && this.selectedTypes.includes(Number(this.shareholderTypeId));
            }
        }"
    >
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
            <label class="grid gap-1">شماره ثبت
                <input name="registration_number" class="w-full rounded-md border-gray-300" value="{{ old('registration_number', $party->registration_number ?? '') }}">
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
            <div class="grid gap-2 md:grid-cols-3">
                @foreach($types as $type)
                    @php($isShareholderType = $type->name === 'shareholder')
                    <label @class([
                        'inline-flex items-center gap-2 rounded-md border px-3 py-2 cursor-pointer transition',
                        'border-emerald-300 bg-emerald-50 ring-1 ring-emerald-200' => $isShareholderType,
                        'border-slate-200 bg-slate-50' => ! $isShareholderType,
                    ])>
                        <input
                            type="checkbox"
                            name="types[]"
                            value="{{ $type->id }}"
                            @checked(in_array($type->id, $selectedTypeIds, true))
                            @change="toggleType({{ $type->id }})"
                        >
                        <span @class(['font-semibold text-emerald-900' => $isShareholderType])>{{ $type->title }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500">برای استفاده در «حساب جاری شرکا»، گزینه «سهامدار/شریک» را فعال کنید.</p>
        </div>

        <div x-show="isShareholder" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 space-y-3">
            <div class="text-sm font-semibold text-emerald-900">تنظیمات حساب جاری شریک</div>
            <label class="grid gap-1">
                <span class="text-sm font-medium text-gray-700">حساب جاری در کدینگ (گروه ۳۲) *</span>
                <select name="partner_chart_account_code" class="w-full rounded-md border-gray-300" :required="isShareholder">
                    <option value="">انتخاب حساب جاری شریک</option>
                    @foreach($partnerAccounts as $account)
                        <option value="{{ $account->code }}" @selected($selectedPartnerCode === $account->code)>
                            {{ $account->code }} - {{ $account->title }}
                        </option>
                    @endforeach
                </select>
                @error('partner_chart_account_code')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                <span class="text-xs text-slate-500">این کد به‌عنوان «کد تفصیل» شخص ذخیره می‌شود و در انتقال‌های حساب جاری شرکا استفاده می‌گردد.</span>
            </label>
            @if($partnerAccounts->isEmpty())
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    حسابی در گروه ۳۲ تعریف نشده است. ابتدا از منوی «کدینگ مالی» حساب جاری شریک را بسازید.
                </div>
            @endif
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
