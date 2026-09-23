@php
    $transactionDate = old('transaction_date')
        ? jalaliDateInputValue(old('transaction_date'))
        : todayJalaliDate();
    $selectedDirection = old('direction', 'deposit');
@endphp

<form method="POST" action="{{ route('partner-current-accounts.store') }}" class="space-y-5">
    @csrf

    @if($errors->has('form'))
        <div class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
            {{ $errors->first('form') }}
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700">نوع انتقال *</span>
                <select name="direction" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="deposit" @selected($selectedDirection === 'deposit')>برداشت شریک (پرداخت از بانک به حساب جاری شریک)</option>
                    <option value="withdraw" @selected($selectedDirection === 'withdraw')>واریز شریک (دریافت در بانک از حساب جاری شریک)</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">شریک/سهامدار *</span>
                <select name="party_id" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب شریک</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" @selected(old('party_id') == $partner->id)>
                            {{ $partner->name }}@if($partner->detail_code) ({{ $partner->detail_code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('party_id')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">حساب بانکی *</span>
                <select name="bank_account_id" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب بانک</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('bank_account_id') == $bank->id)>
                            {{ $bank->code }} - {{ $bank->bank_name }}
                        </option>
                    @endforeach
                </select>
                @error('bank_account_id')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">تاریخ *</span>
                <x-erp.ui.jalali-date-input name="transaction_date" :value="$transactionDate" required class="w-full" />
                @error('transaction_date')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">مبلغ (ریال) *</span>
                <x-erp.ui.money-input name="amount" :value="old('amount')" required />
                @error('amount')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </label>

            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700">شرح</span>
                <textarea name="description" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                @error('description')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </label>
        </div>
    </section>

    <div class="flex justify-end gap-2">
        <a href="{{ route('partner-current-accounts.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
        <button type="submit" class="erp-action-btn erp-action-edit">ثبت انتقال و صدور سند</button>
    </div>
</form>
