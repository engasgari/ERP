<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-xl font-bold text-slate-900">لیست و پرداخت حقوق</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    بعد از محاسبه حقوق، مبالغی مثل پاداش و کسورات را دستی اصلاح کنید، دوره را تایید کنید، سپس با انتخاب حساب بانکی/صندوق پرداخت را ثبت کنید تا سند حسابداری صادر و در صورتحساب شخص بنشیند.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4 xl:min-w-[720px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($y = verta()->year; $y >= 1400; $y--)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ماه</span>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">وضعیت</span>
                    <select wire:model.live="statusFilter" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        <option value="">همه</option>
                        <option value="calculated">محاسبه‌شده</option>
                        <option value="paid">پرداخت‌شده</option>
                        <option value="partial">پرداخت جزئی</option>
                        <option value="failed">ناموفق</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">جستجو</span>
                    <input wire:model.live.debounce.400ms="search" class="mt-1 w-full rounded-md border-gray-300 text-right" placeholder="نام یا کد پرسنلی">
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if($period)
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    وضعیت دوره: {{ ['draft' => 'پیش‌نویس', 'calculated' => 'محاسبه‌شده', 'approved' => 'تاییدشده', 'closed' => 'بسته‌شده'][$period->status] ?? $period->status }}
                </span>
                @if($period->status === 'calculated')
                    <button type="button" wire:click="approvePeriod" wire:confirm="دوره حقوق تایید شود؟" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                        تایید دوره برای پرداخت
                    </button>
                @endif
                <a href="{{ route('payroll.periods.index', ['year' => $year, 'month' => $month]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    بازگشت به دوره‌ها
                </a>
            @else
                <div class="text-sm text-amber-700">برای این ماه هنوز دوره حقوقی ساخته نشده. از صفحه دوره‌های حقوق شروع کنید.</div>
            @endif
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-bold text-slate-900">لیست حقوق ماه</h3>
            <div class="text-sm text-slate-500">{{ formatMoney($calculations->total()) }} نفر</div>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full text-sm">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>ناخالص</th>
                    <th>کسورات</th>
                    <th>خالص</th>
                    <th>پرداخت‌شده</th>
                    <th>مانده</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($calculations as $calculation)
                    @php
                        $paid = (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'));
                        $remaining = max(0, (float) $calculation->net_payable - $paid);
                        $isEditing = $editingId === $calculation->id;
                        $isAdjusted = (bool) data_get($calculation->payslip?->snapshot, 'manually_adjusted', false);
                        $canEdit = ! in_array($calculation->status, ['paid', 'partial', 'failed'], true)
                            && $remaining > 0
                            && $calculation->payments->isEmpty()
                            && $period
                            && $period->status !== 'closed';
                        $canPay = $remaining > 0
                            && $calculation->status !== 'failed'
                            && $period
                            && in_array($period->status, ['approved', 'closed'], true);
                        $canReverse = $paid > 0
                            && $calculation->payments->isNotEmpty()
                            && $period
                            && $period->status !== 'closed';
                        $paymentRecord = $calculation->payments->first();
                    @endphp

                    <tr wire:key="payroll-payment-row-{{ $calculation->id }}">
                        <td class="font-semibold text-slate-900">
                            {{ $calculation->employee?->full_name }}
                            @if($isAdjusted)
                                <div class="mt-1 text-xs font-semibold text-amber-700">اصلاح دستی</div>
                            @endif
                        </td>
                        <td>{{ formatMoney((float) $calculation->gross_salary) }}</td>
                        <td>{{ formatMoney((float) $calculation->total_deductions) }}</td>
                        <td class="font-semibold text-slate-900">{{ formatMoney((float) $calculation->net_payable) }}</td>
                        <td class="text-emerald-700">{{ formatMoney($paid) }}</td>
                        <td class="font-semibold {{ $remaining > 0 ? 'text-blue-700' : 'text-slate-500' }}">{{ formatMoney($remaining) }}</td>
                        <td>
                            @php
                                $labels = [
                                    'calculated' => 'محاسبه‌شده',
                                    'paid' => 'پرداخت‌شده',
                                    'partial' => 'پرداخت جزئی',
                                    'failed' => 'ناموفق',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                {{ $labels[$calculation->status] ?? $calculation->status }}
                            </span>
                        </td>
                        <td>
                            <x-erp.ui.row-actions>
                                @if($canEdit && ! $isEditing)
                                    <x-erp.ui.row-action icon="edit" label="ویرایش مبالغ" wire:click="startEdit({{ $calculation->id }})" />
                                @endif
                                @if($calculation->payslip)
                                    <x-erp.ui.row-action icon="print" label="فیش" :href="route('payslips.print', $calculation->payslip)" target="_blank" />
                                @endif
                                @if($canReverse)
                                    <x-erp.ui.row-action icon="revert" tone="danger" label="برگشت پرداخت"
                                        wire:click="reversePayment({{ $calculation->id }})"
                                        wire:confirm="پرداخت حقوق {{ $calculation->employee?->full_name }} برگشت داده شود؟ سند حسابداری پرداخت عطف می‌شود." />
                                @endif
                            </x-erp.ui.row-actions>
                            @if($paymentRecord)
                                <div class="mt-1 text-xs text-slate-500">
                                    سند پرداخت:
                                    @if($paymentRecord->accountingDocument)
                                        <a href="{{ route('accounting-documents.show', $paymentRecord->accountingDocument) }}" class="font-semibold text-emerald-700 underline" wire:navigate>
                                            {{ $paymentRecord->accountingDocument->number }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>

                    @if($isEditing)
                        <tr class="bg-slate-50" wire:key="payroll-edit-{{ $calculation->id }}">
                            <td colspan="8" class="p-4">
                                <div class="mb-3 text-sm font-semibold text-slate-800">ویرایش مبالغ: {{ $calculation->employee?->full_name }}</div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    @foreach($editLines as $index => $line)
                                        <label class="block rounded-md border border-slate-200 bg-white p-3 text-xs">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-semibold text-slate-800">{{ $line['title'] }}</span>
                                                <span class="rounded-full px-2 py-0.5 {{ $line['type'] === 'earning' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                                                    {{ $line['type'] === 'earning' ? 'مزایا' : 'کسورات' }}
                                                </span>
                                            </div>
                                            <input type="number"
                                                   step="1"
                                                   min="0"
                                                   wire:model="editLines.{{ $index }}.amount"
                                                   @disabled(!empty($line['locked']))
                                                   class="mt-2 w-full rounded-md border-gray-300 text-sm disabled:bg-slate-100">
                                            @if(!empty($line['locked']))
                                                <div class="mt-1 text-[11px] text-slate-500">بیمه/مالیات سیستمی است و اینجا قفل است.</div>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <label class="block text-xs font-medium text-slate-700">
                                        افزودن پاداش
                                        <input type="number" step="1" min="0" wire:model="newBonusAmount" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    </label>
                                    <label class="block text-xs font-medium text-slate-700">
                                        عنوان کسورات دستی
                                        <input type="text" wire:model="newDeductionTitle" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    </label>
                                    <label class="block text-xs font-medium text-slate-700">
                                        مبلغ کسورات دستی
                                        <input type="number" step="1" min="0" wire:model="newDeductionAmount" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    </label>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" wire:click="saveEdit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">ذخیره اصلاحات</button>
                                    <button type="button" wire:click="cancelEdit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">انصراف</button>
                                </div>
                            </td>
                        </tr>
                    @endif

                    @if($canPay)
                        <tr class="bg-emerald-50/40" wire:key="payroll-pay-{{ $calculation->id }}">
                            <td colspan="8" class="p-4">
                                <form wire:submit.prevent="registerPayment({{ $calculation->id }})" class="space-y-3">
                                    <div class="text-sm font-semibold text-emerald-900">
                                        ثبت پرداخت — مانده: {{ formatMoney($remaining) }}
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                                        <label class="block text-xs font-medium text-slate-700">
                                            تاریخ پرداخت
                                            <div class="mt-1">
                                                <x-erp.ui.jalali-date-input
                                                    wire:model.live="paymentDrafts.{{ $calculation->id }}.payment_date"
                                                    placeholder="1405/01/01"
                                                    class="w-full"
                                                />
                                            </div>
                                            @error('paymentDrafts.'.$calculation->id.'.payment_date') <span class="text-red-600">{{ $message }}</span> @enderror
                                        </label>
                                        <label class="block text-xs font-medium text-slate-700">
                                            روش
                                            <select wire:model.live="paymentDrafts.{{ $calculation->id }}.method" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                <option value="bank">بانکی</option>
                                                <option value="cash">نقدی</option>
                                            </select>
                                        </label>
                                        @if(($paymentDrafts[$calculation->id]['method'] ?? 'bank') === 'bank')
                                            <label class="block text-xs font-medium text-slate-700 xl:col-span-2">
                                                حساب بانکی پرداخت‌کننده
                                                <select wire:model.defer="paymentDrafts.{{ $calculation->id }}.bank_account_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                    <option value="">انتخاب کنید</option>
                                                    @foreach($bankAccounts as $bank)
                                                        <option value="{{ $bank->id }}">{{ $bank->bank_name }} — {{ $bank->account_number ?: $bank->code }}</option>
                                                    @endforeach
                                                </select>
                                                @error('paymentDrafts.'.$calculation->id.'.bank_account_id') <span class="text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                        @else
                                            <label class="block text-xs font-medium text-slate-700 xl:col-span-2">
                                                صندوق
                                                <select wire:model.defer="paymentDrafts.{{ $calculation->id }}.cashbox_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                                    <option value="">انتخاب کنید</option>
                                                    @foreach($cashboxes as $cashbox)
                                                        <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('paymentDrafts.'.$calculation->id.'.cashbox_id') <span class="text-red-600">{{ $message }}</span> @enderror
                                            </label>
                                        @endif
                                        <label class="block text-xs font-medium text-slate-700">
                                            مبلغ
                                            <input type="number" step="1" min="1" wire:model.defer="paymentDrafts.{{ $calculation->id }}.amount" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        </label>
                                        <label class="block text-xs font-medium text-slate-700">
                                            شماره مرجع
                                            <input type="text" wire:model.defer="paymentDrafts.{{ $calculation->id }}.reference_number" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        </label>
                                    </div>
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <label class="block flex-1 text-xs font-medium text-slate-700">
                                            توضیحات
                                            <input type="text" wire:model.defer="paymentDrafts.{{ $calculation->id }}.description" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        </label>
                                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                            ثبت پرداخت و صدور سند
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">برای این ماه هنوز محاسبه حقوقی وجود ندارد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $calculations->links() }}</div>
    </div>
</div>
