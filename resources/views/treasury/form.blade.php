<x-app-layout>
    @php
        $isEdit = $isEdit ?? false;
        $transaction = $transaction ?? null;
        $transactionDate = old('transaction_date')
            ? jalaliDateInputValue(old('transaction_date'))
            : ($transaction ? gregorianToJalaliDate($transaction->transaction_date) : todayJalaliDate());
        $types = [
            'deposit' => 'واریز',
            'withdrawal' => 'برداشت',
            'transfer' => 'انتقال بین حساب‌ها',
            'cash_receipt' => 'دریافت نقدی',
            'cash_payment' => 'پرداخت نقدی',
            'bank_receipt' => 'دریافت بانکی',
            'bank_payment' => 'پرداخت بانکی',
        ];
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ $isEdit ? 'ویرایش تراکنش خزانه' : 'ثبت تراکنش خزانه' }}</h2>
    </x-slot>

    <form method="post" action="{{ $isEdit ? route('treasury.update', $transaction) : route('treasury.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid md:grid-cols-3 gap-4">
            <label>نوع تراکنش
                <select name="type" class="w-full">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $transaction?->type ?? 'deposit') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>تاریخ
                <input type="text" name="transaction_date" inputmode="numeric" dir="ltr" placeholder="1405/03/19" value="{{ $transactionDate }}" required class="w-full">
            </label>
            <label>مبلغ (ریال)
                <input type="number" step="0.01" name="amount" value="{{ old('amount', $transaction?->amount) }}" required class="w-full">
            </label>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <label>از حساب خزانه
                <select name="from_treasury_type" class="w-full">
                    <option value="">انتخاب نشده</option>
                    <option value="{{ App\Models\BankAccount::class }}" @selected(old('from_treasury_type', $transaction?->from_treasury_type) === App\Models\BankAccount::class)>بانک</option>
                    <option value="{{ App\Models\Cashbox::class }}" @selected(old('from_treasury_type', $transaction?->from_treasury_type) === App\Models\Cashbox::class)>صندوق</option>
                </select>
            </label>
            <label>شناسه حساب مبدا
                <input name="from_treasury_id" value="{{ old('from_treasury_id', $transaction?->from_treasury_id) }}" class="w-full">
            </label>
            <label>به حساب خزانه
                <select name="to_treasury_type" class="w-full">
                    <option value="">انتخاب نشده</option>
                    <option value="{{ App\Models\BankAccount::class }}" @selected(old('to_treasury_type', $transaction?->to_treasury_type) === App\Models\BankAccount::class)>بانک</option>
                    <option value="{{ App\Models\Cashbox::class }}" @selected(old('to_treasury_type', $transaction?->to_treasury_type) === App\Models\Cashbox::class)>صندوق</option>
                </select>
            </label>
            <label>شناسه حساب مقصد
                <input name="to_treasury_id" value="{{ old('to_treasury_id', $transaction?->to_treasury_id) }}" class="w-full">
            </label>
        </div>

        <label>شخص/شرکت
            <select name="party_id" class="w-full">
                <option value="">بدون شخص</option>
                @foreach($parties as $party)
                    <option value="{{ $party->id }}" @selected(old('party_id', $transaction?->party_id) == $party->id)>{{ $party->name }}</option>
                @endforeach
            </select>
        </label>

        <label>شرح
            <textarea name="description" rows="3" class="w-full">{{ old('description', $transaction?->description) }}</textarea>
        </label>

        <div class="flex justify-end gap-2">
            <a href="{{ route('treasury.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            <button class="erp-action-btn erp-action-edit">{{ $isEdit ? 'ذخیره تغییرات و صدور سند جدید' : 'ثبت تراکنش و صدور سند' }}</button>
        </div>
    </form>
</x-app-layout>
