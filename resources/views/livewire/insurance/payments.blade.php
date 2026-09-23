<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">پرداخت بیمه تأمین اجتماعی</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    پرداخت چند دوره، پرداخت جزئی و جریمه تأخیر را در یک سند حسابداری ثبت کنید.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="toggleCreateForm" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ $showCreateForm ? 'بستن فرم' : 'ثبت پرداخت جدید' }}
                </button>
                <a href="{{ route('insurance.periods.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    دوره‌های بیمه
                </a>
            </div>
        </div>
    </div>

    @if($showCreateForm)
        <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
            <h3 class="mb-4 text-lg font-bold text-slate-900">فرم پرداخت</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">تاریخ پرداخت</span>
                    <input wire:model="paymentDate" type="text" class="mt-1 w-full rounded-md border-gray-300 text-right" placeholder="1404/06/01">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">روش پرداخت</span>
                    <select wire:model.live="method" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        <option value="bank">بانکی</option>
                        <option value="cash">نقدی</option>
                    </select>
                </label>
                @if($method === 'bank')
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">حساب بانکی</span>
                        <select wire:model="bankAccountId" class="mt-1 w-full rounded-md border-gray-300 text-right">
                            <option value="">انتخاب کنید</option>
                            @foreach($bankAccounts as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->bank_name }} - {{ $bank->account_number }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">صندوق</span>
                        <select wire:model="cashboxId" class="mt-1 w-full rounded-md border-gray-300 text-right">
                            <option value="">انتخاب کنید</option>
                            @foreach($cashboxes as $cashbox)
                                <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">شماره پیگیری</span>
                    <input wire:model="referenceNumber" type="text" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">شناسه پرداخت</span>
                    <input wire:model="paymentIdentifier" type="text" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">شماره رسید</span>
                    <input wire:model="receiptNumber" type="text" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
                <label class="block md:col-span-2 xl:col-span-4">
                    <span class="text-sm font-medium text-slate-700">توضیحات</span>
                    <input wire:model="description" type="text" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="erp-ui-data-table min-w-full text-sm">
                    <thead>
                    <tr>
                        <th>انتخاب</th>
                        <th>دوره</th>
                        <th>مانده</th>
                        <th>اصل</th>
                        <th>جریمه</th>
                        <th>سایر</th>
                        <th>عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($payableLiabilities as $liability)
                        @php $draft = $lineDrafts[$liability->id] ?? []; @endphp
                        <tr>
                            <td>
                                <input type="checkbox" wire:model="lineDrafts.{{ $liability->id }}.selected">
                            </td>
                            <td>{{ $liability->period->persian_title }}</td>
                            <td>{{ formatMoney($liability->remainingBalance()) }}</td>
                            <td>
                                <input wire:model="lineDrafts.{{ $liability->id }}.principal_amount" type="number" min="0" step="1" class="w-28 rounded-md border-gray-300 text-right">
                            </td>
                            <td>
                                <input wire:model="lineDrafts.{{ $liability->id }}.penalty_amount" type="number" min="0" step="1" class="w-28 rounded-md border-gray-300 text-right">
                            </td>
                            <td>
                                <input wire:model="lineDrafts.{{ $liability->id }}.other_amount" type="number" min="0" step="1" class="w-28 rounded-md border-gray-300 text-right">
                            </td>
                            <td>
                                <button type="button" wire:click="fillRemaining({{ $liability->id }})" class="text-blue-600 hover:underline">کل مانده</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">بدهی پرداخت‌نشده‌ای وجود ندارد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="registerPayment" wire:confirm="پرداخت بیمه ثبت شود؟" class="rounded-md bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    ثبت پرداخت و سند حسابداری
                </button>
            </div>
        </div>
    @endif

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <h3 class="mb-4 text-lg font-bold text-slate-900">سوابق پرداخت</h3>
        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full text-sm">
                <thead>
                <tr>
                    <th>شماره</th>
                    <th>تاریخ</th>
                    <th>مبلغ کل</th>
                    <th>اصل</th>
                    <th>جریمه</th>
                    <th>روش</th>
                    <th>سند</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>
                            <a href="{{ route('insurance.payments.show', $payment) }}" class="font-semibold text-blue-600 hover:underline">{{ $payment->number }}</a>
                        </td>
                        <td>{{ formatJalaliDateSafe($payment->payment_date) }}</td>
                        <td>{{ formatMoney((float) $payment->total_amount) }}</td>
                        <td>{{ formatMoney((float) $payment->principal_amount) }}</td>
                        <td>{{ formatMoney((float) $payment->penalty_amount) }}</td>
                        <td>{{ $payment->method === 'cash' ? 'نقدی' : 'بانکی' }}</td>
                        <td>
                            @if($payment->accountingDocument)
                                <a href="{{ route('accounting-documents.show', $payment->accountingDocument) }}" class="text-blue-600 hover:underline">{{ $payment->accountingDocument->number }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @can('insurance.payment')
                                <button type="button" wire:click="deletePayment({{ $payment->id }})" wire:confirm="پرداخت حذف و سند عطف شود؟" class="text-red-600 hover:underline">حذف</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">هنوز پرداخت بیمه‌ای ثبت نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $payments->links() }}</div>
    </div>
</div>
