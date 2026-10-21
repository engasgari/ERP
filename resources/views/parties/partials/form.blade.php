@php
    $embedded = $embedded ?? false;
    $isEdit = isset($party) && $party->exists;
    $selectedTypeIds = $selectedTypeIds ?? [];
    $shareholderTypeId = $shareholderTypeId ?? null;
    $selectedPartnerCode = $selectedPartnerCode ?? '';
    $formAction = $formAction ?? ($isEdit ? route('parties.update', $party) : route('parties.store'));
    $cancelUrl = $cancelUrl ?? route('parties.index');
    $lockCustomerType = (bool) ($lockCustomerType ?? false);
    $customerTypeId = (int) ($customerTypeId ?? 0);
@endphp

<form
    method="POST"
    action="{{ $formAction }}"
    class="bg-white rounded-lg shadow-md p-4 sm:p-6 space-y-5 party-editor-form"
    x-data="{
        selectedTypes: @js($selectedTypeIds),
        shareholderTypeId: @js($shareholderTypeId),
        customerTypeId: @js($customerTypeId),
        lockCustomerType: @js($lockCustomerType),
        toggleType(typeId) {
            typeId = Number(typeId);
            if (this.lockCustomerType && typeId === Number(this.customerTypeId)) {
                return;
            }
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

    @if($embedded)
        <div id="party-embedded-errors" class="invoice-error" hidden></div>
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
                @php
                    $isShareholderType = $type->name === 'shareholder';
                    $isCustomerType = $type->name === 'customer';
                    $isLockedCustomer = $lockCustomerType && $isCustomerType;
                @endphp
                {{-- CRM customer form: hide partner/shareholder UI (managed in ERP base info). --}}
                @if($lockCustomerType && $isShareholderType)
                    @if(in_array($type->id, $selectedTypeIds, true))
                        <input type="hidden" name="types[]" value="{{ $type->id }}">
                        @if(filled($selectedPartnerCode))
                            <input type="hidden" name="partner_chart_account_code" value="{{ $selectedPartnerCode }}">
                        @endif
                    @endif
                    @continue
                @endif
                <label @class([
                    'inline-flex items-center gap-2 rounded-md border px-3 py-2 cursor-pointer transition',
                    'border-emerald-300 bg-emerald-50 ring-1 ring-emerald-200' => $isShareholderType,
                    'border-blue-300 bg-blue-50 ring-1 ring-blue-200' => $isLockedCustomer,
                    'border-slate-200 bg-slate-50' => ! $isShareholderType && ! $isLockedCustomer,
                ])>
                    <input
                        type="checkbox"
                        name="types[]"
                        value="{{ $type->id }}"
                        @checked(in_array($type->id, $selectedTypeIds, true))
                        @if($isLockedCustomer) checked onclick="return false;" @endif
                        @change="toggleType({{ $type->id }})"
                    >
                    <span @class([
                        'font-semibold text-emerald-900' => $isShareholderType,
                        'font-semibold text-blue-900' => $isLockedCustomer,
                    ])>{{ $type->title }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-500">
            @if($lockCustomerType)
                گروه «مشتری» برای ثبت از CRM الزامی است و قابل حذف نیست.
            @else
                برای استفاده در «حساب جاری شرکا»، گزینه «سهامدار/شریک» را فعال کنید.
            @endif
        </p>
    </div>

    @unless($lockCustomerType)
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
    @endunless

    <label class="grid gap-1">آدرس
        <textarea name="address" class="w-full rounded-md border-gray-300" rows="3">{{ old('address', $party->address ?? '') }}</textarea>
    </label>

    <label class="grid gap-1">یادداشت
        <textarea name="notes" class="w-full rounded-md border-gray-300" rows="3">{{ old('notes', $party->notes ?? '') }}</textarea>
    </label>

    <div class="flex flex-wrap justify-end gap-2">
        @if($embedded)
            <button type="button" id="party-embedded-cancel" class="erp-action-btn">انصراف</button>
        @else
            <a href="{{ $cancelUrl }}" class="erp-action-btn">بازگشت</a>
        @endif
        <button type="submit" class="erp-action-btn erp-action-edit">{{ $isEdit ? 'ذخیره تغییرات' : 'ثبت' }}</button>
    </div>
</form>

@if($embedded)
    <script>
        (() => {
            const editorForm = document.querySelector('.party-editor-form');
            const embeddedErrors = document.getElementById('party-embedded-errors');
            const saveButton = editorForm?.querySelector('button[type="submit"]');
            const originalSaveText = saveButton?.textContent || 'ذخیره تغییرات';

            document.getElementById('party-embedded-cancel')?.addEventListener('click', () => {
                window.parent.postMessage({ type: 'party-edit-cancel' }, window.location.origin);
            });

            editorForm?.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (embeddedErrors) {
                    embeddedErrors.hidden = true;
                    embeddedErrors.textContent = '';
                }

                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.textContent = 'در حال ذخیره...';
                }

                try {
                    const csrfToken = editorForm.querySelector('input[name="_token"]')?.value
                        || document.querySelector('meta[name="csrf-token"]')?.content
                        || '';

                    const response = await fetch(editorForm.action, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: new FormData(editorForm),
                    });

                    const contentType = response.headers.get('content-type') || '';
                    const isJson = contentType.includes('application/json');
                    let payload = {};

                    if (isJson) {
                        try {
                            payload = await response.json();
                        } catch {
                            payload = {};
                        }
                    }

                    if (!response.ok) {
                        const errors = payload.errors
                            ? Object.values(payload.errors).flat()
                            : [payload.message || `ذخیره انجام نشد. (کد ${response.status})`];
                        throw new Error(errors.filter(Boolean).join(' '));
                    }

                    if (!isJson || !payload.party?.id) {
                        throw new Error(payload.message || 'پاسخ ذخیره نامعتبر بود.');
                    }

                    window.parent.postMessage({
                        type: 'party-saved',
                        message: payload.message || 'مشتری ذخیره شد.',
                        party: payload.party,
                    }, window.location.origin);
                } catch (error) {
                    const message = error.message || 'ذخیره انجام نشد.';

                    if (embeddedErrors) {
                        embeddedErrors.hidden = false;
                        embeddedErrors.textContent = message;
                    }

                    if (typeof window.showErpToast === 'function') {
                        window.showErpToast(message, 'danger');
                    }

                    window.parent.postMessage({
                        type: 'party-validation-error',
                        message,
                    }, window.location.origin);
                } finally {
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.textContent = originalSaveText;
                    }
                }
            });
        })();
    </script>
@endif
