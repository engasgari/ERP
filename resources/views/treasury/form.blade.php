<x-app-layout>
    @php
        $isEdit = $isEdit ?? false;
        $transaction = $transaction ?? null;
        $bankTreasuryType = App\Models\BankAccount::class;
        $cashboxTreasuryType = App\Models\Cashbox::class;
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

    <form method="post" action="{{ $isEdit ? route('treasury.update', $transaction) : route('treasury.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-4" x-data="{
        transactionType: @js(old('type', $transaction?->type ?? 'deposit')),
        fromType: @js(old('from_treasury_type', $transaction?->from_treasury_type ?: '')),
        toType: @js(old('to_treasury_type', $transaction?->to_treasury_type ?: '')),
        bankTreasuryType: @js($bankTreasuryType),
        cashboxTreasuryType: @js($cashboxTreasuryType),
        init() {
            this.$watch('fromType', () => this.syncTreasuryLookups());
            this.$watch('toType', () => this.syncTreasuryLookups());
            this.$watch('transactionType', () => this.applyTransactionTypeDefaults());
            this.applyTransactionTypeDefaults();
            this.syncTreasuryLookups();
        },
        applyTransactionTypeDefaults() {
            switch (this.transactionType) {
                case 'bank_receipt':
                    this.toType = this.bankTreasuryType;
                    break;
                case 'bank_payment':
                    this.fromType = this.bankTreasuryType;
                    break;
                case 'cash_receipt':
                    this.toType = this.cashboxTreasuryType;
                    break;
                case 'cash_payment':
                    this.fromType = this.cashboxTreasuryType;
                    break;
            }
        },
        syncTreasuryLookups() {
            queueMicrotask(() => {
                if (typeof window.ErpUi?.syncLookupMirrorStates === 'function') {
                    window.ErpUi.syncLookupMirrorStates(this.$el);
                }
            });
        },
    }">
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
                <select name="type" class="w-full" x-model="transactionType">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $transaction?->type ?? 'deposit') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>تاریخ
                <x-erp.ui.jalali-date-input name="transaction_date" :value="$transactionDate" required class="w-full" />
            </label>
            <label>مبلغ (ریال)
                <x-erp.ui.money-input name="amount" :value="old('amount', $transaction?->amount)" required class="w-full" />
            </label>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                <div class="text-sm font-bold text-slate-700">حساب خزانه مبدا</div>
                <label>نوع حساب
                    <select name="from_treasury_type" class="w-full" x-model="fromType">
                        <option value="">انتخاب نشده</option>
                        <option value="{{ $bankTreasuryType }}">بانک</option>
                        <option value="{{ $cashboxTreasuryType }}">صندوق</option>
                    </select>
                </label>
                <label x-show="fromType === bankTreasuryType" x-cloak>حساب بانکی
                    <select name="from_treasury_id" class="w-full" :disabled="fromType !== bankTreasuryType">
                        <option value="">انتخاب بانک</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected(old('from_treasury_id', $transaction?->from_treasury_id) == $bank->id && old('from_treasury_type', $transaction?->from_treasury_type) === $bankTreasuryType)>{{ $bank->code }} - {{ $bank->bank_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label x-show="fromType === cashboxTreasuryType" x-cloak>صندوق
                    <select name="from_treasury_id" class="w-full" :disabled="fromType !== cashboxTreasuryType">
                        <option value="">انتخاب صندوق</option>
                        @foreach($cashboxes as $cashbox)
                            <option value="{{ $cashbox->id }}" @selected(old('from_treasury_id', $transaction?->from_treasury_id) == $cashbox->id && old('from_treasury_type', $transaction?->from_treasury_type) === $cashboxTreasuryType)>{{ $cashbox->code }} - {{ $cashbox->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="space-y-3 rounded-xl border border-slate-200 p-4">
                <div class="text-sm font-bold text-slate-700">حساب خزانه مقصد</div>
                <label>نوع حساب
                    <select name="to_treasury_type" class="w-full" x-model="toType">
                        <option value="">انتخاب نشده</option>
                        <option value="{{ $bankTreasuryType }}">بانک</option>
                        <option value="{{ $cashboxTreasuryType }}">صندوق</option>
                    </select>
                </label>
                <label x-show="toType === bankTreasuryType" x-cloak>حساب بانکی
                    <select name="to_treasury_id" class="w-full" :disabled="toType !== bankTreasuryType">
                        <option value="">انتخاب بانک</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}" @selected(old('to_treasury_id', $transaction?->to_treasury_id) == $bank->id && old('to_treasury_type', $transaction?->to_treasury_type) === $bankTreasuryType)>{{ $bank->code }} - {{ $bank->bank_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label x-show="toType === cashboxTreasuryType" x-cloak>صندوق
                    <select name="to_treasury_id" class="w-full" :disabled="toType !== cashboxTreasuryType">
                        <option value="">انتخاب صندوق</option>
                        @foreach($cashboxes as $cashbox)
                            <option value="{{ $cashbox->id }}" @selected(old('to_treasury_id', $transaction?->to_treasury_id) == $cashbox->id && old('to_treasury_type', $transaction?->to_treasury_type) === $cashboxTreasuryType)>{{ $cashbox->code }} - {{ $cashbox->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
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

